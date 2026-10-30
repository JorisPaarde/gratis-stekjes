#!/usr/bin/env bash
# Trigger cPanel Git-deploy + .env + /deploy/run + smoke check.
# Bruikbaar vanuit GitHub Actions én handmatig vanaf een vaste machine.
# Imunify op de host blokkeert vaak onbekende IP's op :2083 (403/time-out zonder JSON);
# GitHub-ranges worden niet gewhitelist — draai dit script dan lokaal/op een allowlisted IP.
#
# Vereiste secrets (exacte namen):
#   CPANEL_HOST, CPANEL_USER, CPANEL_API_TOKEN, DEPLOY_TOKEN, STAGING_BASE_URL
#   STAGING_DB_HOST, STAGING_DB_NAME, STAGING_DB_USER, STAGING_DB_PASS
#   SITE_NAME, SMTP_HOST, SMTP_USER, SMTP_PASS, CRON_TOKEN, ADMIN_PASSWORD
# Optioneel: SMTP_PORT, SMTP_SECURE, SMTP_FROM, CPANEL_BRANCH
# Vaste serverlayout (override via env indien nodig):
#   CPANEL_REPO_ROOT=/home/gratisplanten/repo-staging
#   STAGING_APP_DIR=/home/gratisplanten/app-staging
#   STAGING_DEPLOY_PATH=/home/gratisplanten/staging.gratisplanten.nl
# APP_KEY: geen secret vereist — bestaande .env op server behouden, anders genereren.
# Migratie/caches: POST /deploy/run is leidend (noshell → artisan in .cpanel.yml onbetrouwbaar).
#
# Gebruik:  ./scripts/cpanel-deploy.sh
#            SKIP_ENV_WRITE=1 ./scripts/cpanel-deploy.sh   # alleen pull+deploy+migrate
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=scripts/dotenv-quote.sh
source "${ROOT}/scripts/dotenv-quote.sh"

mask() {
  local v=${1-}
  if [ -n "$v" ]; then
    printf '%s\n' "::add-mask::${v}"
  fi
}

die() {
  printf '%s\n' "$*" >&2
  exit 1
}

notice() {
  if [ -n "${GITHUB_ACTIONS:-}" ]; then
    printf '%s\n' "::notice::$*"
  else
    printf '%s\n' "$*"
  fi
}

error_ann() {
  if [ -n "${GITHUB_ACTIONS:-}" ]; then
    printf '%s\n' "::error::$*"
  else
    printf '%s\n' "$*" >&2
  fi
}

require_var() {
  local name=$1
  if [ -z "${!name:-}" ]; then
    die "Verplicht: env-var ${name} ontbreekt. Niets half gedaan."
  fi
}

# --- secrets masken (nooit loggen) ---
mask "${CPANEL_API_TOKEN:-}"
mask "${DEPLOY_TOKEN:-}"
mask "${STAGING_DB_PASS:-}"
mask "${SMTP_PASS:-}"
mask "${ADMIN_PASSWORD:-}"
mask "${CRON_TOKEN:-}"
mask "${APP_KEY:-}"

CPANEL_BRANCH="${CPANEL_BRANCH:-deploy-staging}"
CPANEL_RETRIES="${CPANEL_RETRIES:-5}"
CPANEL_RETRY_SLEEP="${CPANEL_RETRY_SLEEP:-8}"
DEPLOY_POLL_MAX="${DEPLOY_POLL_MAX:-60}"
DEPLOY_POLL_SLEEP="${DEPLOY_POLL_SLEEP:-5}"
# Vaste Hoasted-layout (noshell); alleen overschrijven als je bewust een andere clone test.
CPANEL_REPO_ROOT="${CPANEL_REPO_ROOT:-/home/gratisplanten/repo-staging}"
STAGING_APP_DIR="${STAGING_APP_DIR:-/home/gratisplanten/app-staging}"
STAGING_DEPLOY_PATH="${STAGING_DEPLOY_PATH:-/home/gratisplanten/staging.gratisplanten.nl}"

# Skip-modus: geen cPanel-secrets → stille exit 0 (CI blijft groen).
if [ -z "${CPANEL_HOST:-}" ] && [ -z "${CPANEL_USER:-}" ] && [ -z "${CPANEL_API_TOKEN:-}" ]; then
  notice "cPanel-secrets ontbreken (CPANEL_HOST/USER/API_TOKEN) — cPanel-deploy overgeslagen."
  exit 0
fi

require_var CPANEL_HOST
require_var CPANEL_USER
require_var CPANEL_API_TOKEN
require_var STAGING_BASE_URL
require_var DEPLOY_TOKEN
require_var STAGING_DB_HOST
require_var STAGING_DB_NAME
require_var STAGING_DB_USER
require_var STAGING_DB_PASS
require_var SMTP_HOST
require_var SMTP_USER
require_var SMTP_PASS
require_var CRON_TOKEN
require_var ADMIN_PASSWORD

CPANEL_HOST="${CPANEL_HOST%/}"
APP_DIR="${STAGING_APP_DIR%/}"
DOCROOT="${STAGING_DEPLOY_PATH%/}"
BASE_URL="${STAGING_BASE_URL%/}"

# UAPI call met retries (Imunify blokkeert vaak GitHub Actions-IP's op :2083).
# Args: module function [curl -d args...]
# Zet UAPI_BODY (raw) en UAPI_HTTP (status). Exit 1 bij blijvende fout.
cpanel_uapi() {
  local module=$1 func=$2
  shift 2
  local attempt=1
  local body http tmp
  tmp="$(mktemp)"
  while [ "$attempt" -le "$CPANEL_RETRIES" ]; do
    http=0
    body=""
    set +e
    http=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 20 --max-time 120 \
      -H "Authorization: cpanel ${CPANEL_USER}:${CPANEL_API_TOKEN}" \
      -H "Content-Type: application/x-www-form-urlencoded" \
      -X POST "${CPANEL_HOST}/execute/${module}/${func}" \
      "$@" 2>/dev/null)
    local curl_ec=$?
    set -e
    body="$(cat "$tmp" 2>/dev/null || true)"

    if [ "$curl_ec" -ne 0 ] || [ "$http" = "000" ] || [ -z "$http" ]; then
      notice "cPanel UAPI ${module}::${func}: poging ${attempt}/${CPANEL_RETRIES} — time-out/connectie (http=${http:-?})"
    elif [ "$http" = "403" ] || [ "$http" = "401" ] || [ "$http" = "429" ] || [ "$http" = "503" ]; then
      notice "cPanel UAPI ${module}::${func}: poging ${attempt}/${CPANEL_RETRIES} — HTTP ${http}"
      if ! printf '%s' "$body" | head -c 200 | grep -q '{'; then
        if [ "$attempt" -ge "$CPANEL_RETRIES" ]; then
          rm -f "$tmp"
          error_ann "cPanel API geblokkeerd, waarschijnlijk Imunify/IP (HTTP ${http}, geen JSON). Draai scripts/cpanel-deploy.sh vanaf een allowlisted IP of workflow_dispatch vanaf een andere runner."
          return 1
        fi
      fi
    elif ! printf '%s' "$body" | python3 -c 'import json,sys; json.load(sys.stdin)' 2>/dev/null; then
      notice "cPanel UAPI ${module}::${func}: poging ${attempt}/${CPANEL_RETRIES} — antwoord is geen JSON (HTTP ${http})"
      # Diagnose: alleen <title> en byte-lengte, nooit de body zelf
      local title
      title="$(printf '%s' "$body" | tr '\n' ' ' | grep -oiE '<title>[^<]{0,120}</title>' | head -1 || true)"
      notice "cPanel UAPI ${module}::${func}: body ${#body} bytes, titel: ${title:-geen}"
      if [ "$attempt" -ge "$CPANEL_RETRIES" ]; then
        rm -f "$tmp"
        error_ann "cPanel API geblokkeerd, waarschijnlijk Imunify/IP (HTTP ${http}, geen JSON). Draai scripts/cpanel-deploy.sh vanaf een allowlisted IP of workflow_dispatch vanaf een andere runner."
        return 1
      fi
    else
      local status
      status="$(printf '%s' "$body" | python3 -c 'import json,sys; r=json.load(sys.stdin); print(r.get("result",{}).get("status", r.get("status",0)))' 2>/dev/null || echo 0)"
      UAPI_BODY="$body"
      UAPI_HTTP="$http"
      rm -f "$tmp"
      if [ "$status" != "1" ]; then
        local err
        err="$(printf '%s' "$body" | python3 -c 'import json,sys; r=json.load(sys.stdin); e=r.get("result",{}).get("errors") or []; print("; ".join(e) if e else "status=0")' 2>/dev/null || echo status=0)"
        error_ann "cPanel UAPI ${module}::${func} faalde: ${err}"
        return 1
      fi
      return 0
    fi

    sleep $((CPANEL_RETRY_SLEEP * attempt))
    attempt=$((attempt + 1))
  done
  rm -f "$tmp"
  error_ann "cPanel API geblokkeerd, waarschijnlijk Imunify/IP (na ${CPANEL_RETRIES} pogingen)."
  return 1
}

json_get() {
  # json_get '.result.data.content' <<<"$json"
  local expr=$1
  printf '%s' "${2:-$UAPI_BODY}" | python3 -c "import json,sys; d=json.load(sys.stdin)
def g(o,p):
  for k in p.lstrip('.').split('.'):
    if k=='': continue
    if isinstance(o,list):
      o=o[int(k)]
    else:
      o=o.get(k)
  if o is None: print('');
  elif isinstance(o,(dict,list)): print(json.dumps(o))
  else: print(o)
g(d,'$expr')"
}

# --- .env schrijven ---
write_env() {
  if [ "${SKIP_ENV_WRITE:-}" = "1" ]; then
    notice "SKIP_ENV_WRITE=1 — .env niet aangepast."
    return 0
  fi

  local existing_key="" content="" raw_key=""
  # Bestaande APP_KEY bewaren (Fileman); ontbrekend bestand is ok.
  if cpanel_uapi Fileman get_file_content \
      --data-urlencode "dir=${APP_DIR}" \
      --data-urlencode "file=.env" \
      --data-urlencode "to_charset=utf-8"; then
    content="$(json_get '.result.data.content')"
    if [ -n "$content" ]; then
      raw_key="$(printf '%s\n' "$content" | grep -E '^APP_KEY=' | head -1 | cut -d= -f2- || true)"
      existing_key="$(dotenv_unquote "$raw_key")"
    fi
  else
    notice "Geen bestaande .env (of leesfout) — nieuwe APP_KEY-logica."
  fi
  # Wis content uit geheugen-pad (niet loggen).
  content=""
  raw_key=""

  local APP_KEY_VALUE
  if [ -n "${APP_KEY:-}" ]; then
    APP_KEY_VALUE="$APP_KEY"
  elif [ -n "$existing_key" ]; then
    APP_KEY_VALUE="$existing_key"
  else
    APP_KEY_VALUE="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
  fi
  mask "$APP_KEY_VALUE"
  existing_key=""

  local NAME ENC MAIL_SCHEME MAIL_FROM
  NAME="${SITE_NAME:-Gratis planten}"
  ENC=$(printf '%s' "${SMTP_SECURE:-ssl}" | tr '[:upper:]' '[:lower:]')
  case "${ENC}" in
    ssl|smtps) MAIL_SCHEME=smtps ;;
    tls|starttls) MAIL_SCHEME=smtp ;;
    *) MAIL_SCHEME=smtps ;;
  esac
  MAIL_FROM="${SMTP_FROM:-${SMTP_USER}}"

  local envfile
  envfile="$(mktemp)"
  umask 077
  {
    dotenv_line APP_NAME "$NAME"
    dotenv_line SITE_NAME "$NAME"
    dotenv_line APP_ENV staging
    dotenv_line APP_KEY "$APP_KEY_VALUE"
    dotenv_line APP_DEBUG false
    dotenv_line APP_URL "$BASE_URL"
    dotenv_line NOINDEX true
    dotenv_line APP_LOCALE nl
    dotenv_line APP_FALLBACK_LOCALE nl
    dotenv_line LOG_CHANNEL stack
    dotenv_line LOG_LEVEL error
    dotenv_line DB_CONNECTION mysql
    dotenv_line DB_HOST "$STAGING_DB_HOST"
    dotenv_line DB_PORT 3306
    dotenv_line DB_DATABASE "$STAGING_DB_NAME"
    dotenv_line DB_USERNAME "$STAGING_DB_USER"
    dotenv_line DB_PASSWORD "$STAGING_DB_PASS"
    dotenv_line SESSION_DRIVER file
    dotenv_line SESSION_SECURE_COOKIE true
    dotenv_line QUEUE_CONNECTION database
    dotenv_line CACHE_STORE file
    dotenv_line MAIL_MAILER smtp
    dotenv_line MAIL_SCHEME "$MAIL_SCHEME"
    dotenv_line MAIL_HOST "$SMTP_HOST"
    dotenv_line MAIL_PORT "${SMTP_PORT:-465}"
    dotenv_line MAIL_USERNAME "$SMTP_USER"
    dotenv_line MAIL_PASSWORD "$SMTP_PASS"
    dotenv_line MAIL_FROM_ADDRESS "$MAIL_FROM"
    dotenv_line MAIL_FROM_NAME "$NAME"
    dotenv_line CRON_TOKEN "$CRON_TOKEN"
    dotenv_line ADMIN_PASSWORD "$ADMIN_PASSWORD"
    dotenv_line DEPLOY_TOKEN "$DEPLOY_TOKEN"
  } > "$envfile"

  cpanel_uapi Fileman save_file_content \
    --data-urlencode "dir=${APP_DIR}" \
    --data-urlencode "file=.env" \
    --data-urlencode "content@${envfile}" \
    --data-urlencode "from_charset=UTF-8" \
    --data-urlencode "to_charset=UTF-8" \
    || { rm -f "$envfile"; die "Schrijven van .env via Fileman mislukt."; }

  rm -f "$envfile"
  # Best-effort chmod 600
  cpanel_uapi Fileman set_permissions \
    --data-urlencode "path=${APP_DIR}/.env" \
    --data-urlencode "mode=0600" 2>/dev/null \
    || notice ".env-rechten zetten overgeslagen (Fileman::set_permissions niet beschikbaar)."

  notice "Staging .env geschreven via UAPI (waarden niet gelogd)."
}

trigger_git_deploy() {
  notice "VersionControl::update (${CPANEL_REPO_ROOT}, branch=${CPANEL_BRANCH})"
  cpanel_uapi VersionControl update \
    --data-urlencode "repository_root=${CPANEL_REPO_ROOT}" \
    --data-urlencode "branch=${CPANEL_BRANCH}"

  notice "VersionControlDeployment::create"
  cpanel_uapi VersionControlDeployment create \
    --data-urlencode "repository_root=${CPANEL_REPO_ROOT}"

  local deploy_id
  deploy_id="$(json_get '.result.data.deploy_id')"
  if [ -z "$deploy_id" ] || [ "$deploy_id" = "null" ]; then
    die "Geen deploy_id terug van VersionControlDeployment::create."
  fi
  notice "Deploy gestart (id=${deploy_id}), poll tot klaar…"

  local i=0 state=""
  while [ "$i" -lt "$DEPLOY_POLL_MAX" ]; do
    sleep "$DEPLOY_POLL_SLEEP"
    cpanel_uapi VersionControlDeployment retrieve || return 1
    state="$(printf '%s' "$UAPI_BODY" | python3 -c "
import json,sys
want=str('''${deploy_id}''')
data=json.load(sys.stdin).get('result',{}).get('data') or []
for row in data:
  if str(row.get('deploy_id',''))==want:
    ts=row.get('timestamps') or {}
    if ts.get('failed') or ts.get('canceled'):
      print('failed'); break
    if ts.get('succeeded'):
      print('ok'); break
    print('pending'); break
else:
  print('missing')
")"
    case "$state" in
      ok) notice "cPanel-deploy geslaagd (id=${deploy_id})."; return 0 ;;
      failed) die "cPanel-deploy mislukt (id=${deploy_id}). Zie ~/.cpanel/logs/vc_*_git_deploy.log op de server." ;;
      missing) notice "Deploy ${deploy_id} nog niet in retrieve-lijst…" ;;
      *) ;; # pending
    esac
    i=$((i + 1))
  done
  die "cPanel-deploy time-out na polling (id=${deploy_id})."
}

call_deploy_endpoint() {
  local url="${BASE_URL}/deploy/run"
  local code body tmp
  tmp="$(mktemp)"
  set +e
  code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 180 \
    -X POST -H "Authorization: Bearer ${DEPLOY_TOKEN}" \
    -H "Accept: application/json" \
    "$url")
  set -e
  body="$(cat "$tmp" 2>/dev/null || true)"
  rm -f "$tmp"
  if [ "$code" != "200" ]; then
    # Geen body loggen (kan stack traces bevatten); alleen status + ok-flag.
    local ok
    ok="$(printf '%s' "$body" | python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print(d.get("ok"), d.get("error",""))
except Exception:
 print("?", "non-json")
' 2>/dev/null || echo '? non-json')"
    die "POST /deploy/run faalde (HTTP ${code}, ${ok})."
  fi
  notice "POST /deploy/run → 200"
}

smoke_check() {
  local ver code tmp
  ver="$(tr -d '[:space:]' < "${ROOT}/VERSION")"
  tmp="$(mktemp)"
  set +e
  code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 60 "${BASE_URL}/")
  set -e
  echo "staging http ${code}"
  if [ "$code" != "200" ]; then
    rm -f "$tmp"
    die "Smoke: homepage HTTP ${code}, verwacht 200."
  fi
  grep -q 'Gratis planten' "$tmp" || { rm -f "$tmp"; die "Smoke: merknaam ontbreekt."; }
  grep -q "Versie ${ver}" "$tmp" || { rm -f "$tmp"; die "Smoke: footer-versie ${ver} ontbreekt."; }
  if ! grep -qi 'noindex' "$tmp"; then
    notice "LET OP: geen noindex-meta in HTML."
  fi
  local headers
  headers="$(mktemp)"
  curl -sSI --connect-timeout 15 --max-time 30 "${BASE_URL}/" > "$headers" || true
  if ! grep -qi '^x-robots-tag:.*noindex' "$headers"; then
    notice "LET OP: geen X-Robots-Tag: noindex"
  fi
  rm -f "$tmp" "$headers"
  notice "Smoke OK (versie ${ver})."
}

# Volgorde: eerst code-deploy (maakt APP_DIR), dan .env, dan migrate-endpoint.
trigger_git_deploy
write_env
call_deploy_endpoint
smoke_check
notice "cPanel staging-deploy voltooid."
