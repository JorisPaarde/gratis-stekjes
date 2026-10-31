#!/usr/bin/env bash
# Bouw-artefact (zip) → chunked HTTPS-upload naar deploy-hook.php → POST /deploy/run → smoke.
# Werkt voor staging én production (APP_ENV_NAME / BASE_URL / DB_* via env).
#
# Vereiste env:
#   DEPLOY_HOOK_TOKEN, DEPLOY_TOKEN, BASE_URL
#   DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#   SMTP_HOST, SMTP_USER, SMTP_PASS, CRON_TOKEN, ADMIN_PASSWORD
# Optioneel: SITE_NAME, SMTP_PORT, SMTP_SECURE, SMTP_FROM, APP_KEY, APP_ENV_NAME (staging|production)
#            ARTIFACT_PATH (bestaande zip), RELEASE_ID, SKIP_SMOKE=1, SKIP_MIGRATE=1
#            HOOK_PATH (default /deploy-hook.php)
#            IMPORT_ENABLED (true|false → DEPLOY_IMPORT_ENABLED; default false)
#            BACKUP_ENCRYPTION_KEY (apart van APP_KEY; leeg → hook behoudt bestaande .env-waarde)
#            BACKUP_RETAIN_DAYS (default 30)
#            ADMIN_DASHBOARD_EMAILS (CSV; alleen schrijven als gezet; leeg → hook behoudt bestaande)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=scripts/dotenv-quote.sh
source "${ROOT}/scripts/dotenv-quote.sh"

CHUNK_SIZE=5242880 # 5 MiB

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
    die "Verplicht: env-var ${name} ontbreekt."
  fi
}

print_install_hint() {
  error_ann "Deploy-hook niet gevonden (HTTP 404) op ${BASE_URL}${HOOK_PATH}."
  error_ann "Eenmalige handmatige installatie (staging):"
  error_ann "1) ./scripts/make-deploy-hook-config.sh staging   # zet DEPLOY_HOOK_TOKEN, print config"
  error_ann "2) cPanel File Manager: map /home/gratisplanten/.deploy mode 0700"
  error_ann "3) Plak config als /home/gratisplanten/.deploy/staging.php mode 0600"
  error_ann "4) Upload deploy/hook/deploy-hook.php en deploy/hook/lib.php naar de staging-docroot"
  error_ann "5) Zet overige staging-secrets (DEPLOY_TOKEN, STAGING_BASE_URL, STAGING_DB_*, SMTP_*, …)"
  error_ann "Volledige stappen: deploy/hook/INSTALL.md"
}

mask "${DEPLOY_HOOK_TOKEN:-}"
mask "${DEPLOY_TOKEN:-}"
mask "${DB_PASSWORD:-}"
mask "${SMTP_PASS:-}"
mask "${ADMIN_PASSWORD:-}"
mask "${ADMIN_DASHBOARD_EMAILS:-}"
mask "${CRON_TOKEN:-}"
mask "${APP_KEY:-}"
mask "${BACKUP_ENCRYPTION_KEY:-}"

require_var DEPLOY_HOOK_TOKEN
require_var DEPLOY_TOKEN
require_var BASE_URL
require_var DB_HOST
require_var DB_DATABASE
require_var DB_USERNAME
require_var DB_PASSWORD
require_var SMTP_HOST
require_var SMTP_USER
require_var SMTP_PASS
require_var CRON_TOKEN
require_var ADMIN_PASSWORD

APP_ENV_NAME="${APP_ENV_NAME:-staging}"
HOOK_PATH="${HOOK_PATH:-/deploy-hook.php}"
BASE_URL="${BASE_URL%/}"
HOOK_URL="${BASE_URL}${HOOK_PATH}"
RELEASE_ID="${RELEASE_ID:-$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || date +%Y%m%d%H%M%S)}"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

# --- Artefact bouwen of hergebruiken ---
ARTIFACT="${ARTIFACT_PATH:-}"
if [ -z "$ARTIFACT" ] || [ ! -f "$ARTIFACT" ]; then
  notice "Artefact zippen…"
  STAGE="${WORKDIR}/stage"
  mkdir -p "$STAGE"
  # Kopieer bron → stage zonder secrets/tests/node_modules (tar, geen rsync nodig).
  tar -C "${ROOT}" \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='.cpanel.yml' \
    --exclude='.phpunit.result.cache' \
    --exclude='phpunit.xml' \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache' \
    --exclude='storage/framework/sessions' \
    --exclude='storage/framework/views' \
    --exclude='storage/app' \
    --exclude='storage/backups' \
    --exclude='deploy/hook/INSTALL.md' \
    -cf - . | tar -C "${STAGE}" -xf -

  # Lege storage-structuur behouden in zip (hook vervangt door shared symlink).
  mkdir -p \
    "${STAGE}/storage/framework/cache" \
    "${STAGE}/storage/framework/sessions" \
    "${STAGE}/storage/framework/views" \
    "${STAGE}/storage/logs" \
    "${STAGE}/storage/app/public" \
    "${STAGE}/storage/backups" \
    "${STAGE}/bootstrap/cache"

  test -f "${STAGE}/public/build/manifest.json" || die "public/build/manifest.json ontbreekt — eerst vite build."
  test -d "${STAGE}/vendor" || die "vendor/ ontbreekt — eerst composer --no-dev."

  # Env-aware robots.txt in de zip → hook sync't naar docroot (hosting-fallback anders).
  chmod +x "${ROOT}/scripts/write-robots-txt.sh"
  APP_ENV_NAME="$APP_ENV_NAME" "${ROOT}/scripts/write-robots-txt.sh" "${STAGE}/public/robots.txt"
  test -s "${STAGE}/public/robots.txt" || die "public/robots.txt ontbreekt na write-robots-txt."

  ARTIFACT="${WORKDIR}/release-${RELEASE_ID}.zip"
  (
    cd "$STAGE"
    zip -qr "$ARTIFACT" .
    # Expliciet toevoegen/updaten (gitignore mag de check niet verwarren; zip neemt het gewoon mee).
    zip -q "$ARTIFACT" public/robots.txt
  )
fi

# True als public/robots.txt als zip-entry bestaat (unzip -l zet spaties vóór de naam — geen leading /).
artifact_has_robots() {
  local z=$1
  # zipinfo -1 = alleen paden; fallback: laatste veld van unzip -l.
  if command -v zipinfo >/dev/null 2>&1; then
    zipinfo -1 "$z" 2>/dev/null | grep -Fxq 'public/robots.txt' && return 0
  fi
  unzip -l "$z" 2>/dev/null | awk '{print $NF}' | grep -Fxq 'public/robots.txt'
}

# Bestaande ARTIFACT_PATH of rare zip-omit: injecteer public/robots.txt.
if ! artifact_has_robots "$ARTIFACT"; then
  notice "Artefact mist public/robots.txt — injecteren…"
  ROBOTS_INJECT="${WORKDIR}/robots-inject"
  mkdir -p "${ROBOTS_INJECT}/public"
  chmod +x "${ROOT}/scripts/write-robots-txt.sh"
  APP_ENV_NAME="$APP_ENV_NAME" "${ROOT}/scripts/write-robots-txt.sh" "${ROBOTS_INJECT}/public/robots.txt"
  (
    cd "$ROBOTS_INJECT"
    zip -q "$ARTIFACT" public/robots.txt
  )
fi
artifact_has_robots "$ARTIFACT" || die "Artefact bevat geen public/robots.txt."
notice "Artefact bevat public/robots.txt."

SIZE="$(wc -c < "$ARTIFACT" | tr -d ' ')"
TOTAL_SHA="$(sha256sum "$ARTIFACT" | awk '{print $1}')"
notice "Artefact: ${SIZE} bytes, sha256=${TOTAL_SHA:0:12}…"

# Chunks
CHUNK_DIR="${WORKDIR}/chunks"
mkdir -p "$CHUNK_DIR"
split -b "$CHUNK_SIZE" -d -a 5 "$ARTIFACT" "${CHUNK_DIR}/chunk_"
mapfile -t CHUNKS < <(ls -1 "$CHUNK_DIR"/chunk_* | sort)
CHUNK_COUNT="${#CHUNKS[@]}"
notice "Chunks: ${CHUNK_COUNT} × tot ${CHUNK_SIZE} bytes"

# --- .env genereren (zelfde quoting als cpanel-deploy.sh) ---
NAME="${SITE_NAME:-Gratis planten}"
ENC=$(printf '%s' "${SMTP_SECURE:-ssl}" | tr '[:upper:]' '[:lower:]')
case "${ENC}" in
  ssl|smtps) MAIL_SCHEME=smtps ;;
  tls|starttls) MAIL_SCHEME=smtp ;;
  *) MAIL_SCHEME=smtps ;;
esac
MAIL_FROM="${SMTP_FROM:-${SMTP_USER}}"

NOINDEX_VAL=true
DEPLOY_ENDPOINT_ENABLED_VAL=false
if [ "$APP_ENV_NAME" = "production" ]; then
  NOINDEX_VAL=false
  DEPLOY_ENDPOINT_ENABLED_VAL=true
fi

ENVFILE="${WORKDIR}/.env.upload"
umask 077
{
  dotenv_line APP_NAME "$NAME"
  dotenv_line SITE_NAME "$NAME"
  dotenv_line APP_ENV "$APP_ENV_NAME"
  # APP_KEY: optioneel secret alleen voor first-install. Hook behoudt altijd een
  # bestaande server-APP_KEY (nooit overschrijven), ook als dit secret anders is.
  if [ -n "${APP_KEY:-}" ]; then
    dotenv_line APP_KEY "$APP_KEY"
  else
    printf '%s\n' 'APP_KEY='
  fi
  dotenv_line APP_DEBUG false
  dotenv_line APP_URL "$BASE_URL"
  dotenv_line NOINDEX "$NOINDEX_VAL"
  dotenv_line APP_LOCALE nl
  dotenv_line APP_FALLBACK_LOCALE nl
  dotenv_line LOG_CHANNEL stack
  dotenv_line LOG_LEVEL error
  dotenv_line DB_CONNECTION mysql
  dotenv_line DB_HOST "$DB_HOST"
  dotenv_line DB_PORT "${DB_PORT:-3306}"
  dotenv_line DB_DATABASE "$DB_DATABASE"
  dotenv_line DB_USERNAME "$DB_USERNAME"
  dotenv_line DB_PASSWORD "$DB_PASSWORD"
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
  # Dashboard-accounts: alleen schrijven als secret gezet; anders behoudt de hook de bestaande waarde.
  if [ -n "${ADMIN_DASHBOARD_EMAILS:-}" ]; then
    dotenv_line ADMIN_DASHBOARD_EMAILS "$ADMIN_DASHBOARD_EMAILS"
  fi
  dotenv_line DEPLOY_TOKEN "$DEPLOY_TOKEN"
  dotenv_line DEPLOY_ENDPOINT_ENABLED "$DEPLOY_ENDPOINT_ENABLED_VAL"
  # Backup-sleutel: alleen schrijven als secret gezet; anders behoudt de hook de bestaande waarde.
  if [ -n "${BACKUP_ENCRYPTION_KEY:-}" ]; then
    dotenv_line BACKUP_ENCRYPTION_KEY "$BACKUP_ENCRYPTION_KEY"
  else
    printf '%s\n' 'BACKUP_ENCRYPTION_KEY='
  fi
  dotenv_line BACKUP_RETAIN_DAYS "${BACKUP_RETAIN_DAYS:-30}"
  # VERHUIS: workflow_dispatch import_enabled=true zet dit aan; tag-pushes laten IMPORT_ENABLED leeg → false.
  case "$(printf '%s' "${IMPORT_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')" in
    1|true|yes) _IMPORT_ENABLED_VAL=true ;;
    *) _IMPORT_ENABLED_VAL=false ;;
  esac
  dotenv_line DEPLOY_IMPORT_ENABLED "$_IMPORT_ENABLED_VAL"
  # Homepage v2: aan op staging (preview), uit op production tot Joris vrijgeeft.
  if [ "$APP_ENV_NAME" = "staging" ]; then
    dotenv_line HOMEPAGE_V2 true
  else
    # Prod: alleen true als secret/var expliciet gezet; anders false (oude homepage).
    case "$(printf '%s' "${HOMEPAGE_V2:-false}" | tr '[:upper:]' '[:lower:]')" in
      1|true|yes) _HOMEPAGE_V2_VAL=true ;;
      *) _HOMEPAGE_V2_VAL=false ;;
    esac
    dotenv_line HOMEPAGE_V2 "$_HOMEPAGE_V2_VAL"
  fi
  if [ -n "${HOMEPAGE_HERO_VARIANT:-}" ]; then
    dotenv_line HOMEPAGE_HERO_VARIANT "$HOMEPAGE_HERO_VARIANT"
  fi
} > "$ENVFILE"

# --- HTTP helpers ---
# hook_post fields...  → zet HOOK_HTTP + HOOK_BODY; gebruikt curl form fields
hook_curl() {
  local tmp http
  tmp="$(mktemp)"
  set +e
  http=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 20 --max-time 300 \
    -H "Authorization: Bearer ${DEPLOY_HOOK_TOKEN}" \
    -H "Accept: application/json" \
    "$@" 2>/dev/null)
  local ec=$?
  set -e
  HOOK_HTTP="$http"
  HOOK_BODY="$(cat "$tmp" 2>/dev/null || true)"
  rm -f "$tmp"
  if [ "$ec" -ne 0 ] || [ -z "$HOOK_HTTP" ] || [ "$HOOK_HTTP" = "000" ]; then
    die "curl naar deploy-hook faalde (connectie)."
  fi
  if [ "$HOOK_HTTP" = "404" ]; then
    print_install_hint
    exit 1
  fi
}

# --- init ---
notice "Hook init → ${HOOK_URL}"
hook_curl -X POST \
  -F "action=init" \
  -F "release=${RELEASE_ID}" \
  -F "total_size=${SIZE}" \
  -F "total_sha256=${TOTAL_SHA}" \
  -F "chunk_count=${CHUNK_COUNT}" \
  "$HOOK_URL"

if [ "$HOOK_HTTP" != "200" ]; then
  error_ann "Hook init HTTP ${HOOK_HTTP}"
  # Body mag error-code bevatten, geen secrets.
  ok="$(printf '%s' "$HOOK_BODY" | python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print(d.get("error","?"))
except Exception:
 print("non-json")' 2>/dev/null || echo non-json)"
  die "Hook init faalde: ${ok}"
fi

UPLOAD_ID="$(printf '%s' "$HOOK_BODY" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("upload_id",""))')"
if [ -z "$UPLOAD_ID" ]; then
  die "Hook init: geen upload_id."
fi
notice "upload_id=${UPLOAD_ID}"

# --- chunks ---
idx=0
for chunk_path in "${CHUNKS[@]}"; do
  csha="$(sha256sum "$chunk_path" | awk '{print $1}')"
  hook_curl -X POST \
    -F "action=chunk" \
    -F "upload_id=${UPLOAD_ID}" \
    -F "chunk_index=${idx}" \
    -F "chunk_sha256=${csha}" \
    -F "chunk=@${chunk_path};type=application/octet-stream" \
    "$HOOK_URL"
  if [ "$HOOK_HTTP" != "200" ]; then
    err="$(printf '%s' "$HOOK_BODY" | python3 -c 'import json,sys
try:
 print(json.load(sys.stdin).get("error","?"))
except Exception:
 print("non-json")' 2>/dev/null || echo non-json)"
    die "Hook chunk ${idx} faalde (HTTP ${HOOK_HTTP}: ${err})."
  fi
  idx=$((idx + 1))
done
notice "Alle ${CHUNK_COUNT} chunks geüpload."

# --- finish ---
hook_curl -X POST \
  -F "action=finish" \
  -F "upload_id=${UPLOAD_ID}" \
  -F "env=<${ENVFILE}" \
  "$HOOK_URL"
if [ "$HOOK_HTTP" != "200" ]; then
  err="$(printf '%s' "$HOOK_BODY" | python3 -c 'import json,sys
try:
 print(json.load(sys.stdin).get("error","?"))
except Exception:
 print("non-json")' 2>/dev/null || echo non-json)"
  die "Hook finish faalde (HTTP ${HOOK_HTTP}: ${err})."
fi
METHOD="$(printf '%s' "$HOOK_BODY" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("method",""))' 2>/dev/null || true)"
notice "Hook finish OK (extract=${METHOD:-?})."

# Soft hook-call: zet HOOK_HTTP/HOOK_BODY, faalt nooit hard (smoke-retry pad).
hook_curl_soft() {
  local tmp http
  tmp="$(mktemp)"
  set +e
  http=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 60 \
    -H "Authorization: Bearer ${DEPLOY_HOOK_TOKEN}" \
    -H "Accept: application/json" \
    "$@" 2>/dev/null)
  set -e
  HOOK_HTTP="${http:-000}"
  HOOK_BODY="$(cat "$tmp" 2>/dev/null || true)"
  rm -f "$tmp"
  return 0
}

# LiteSpeed: herschrijf ~/.lsphp_restart.txt in account-home (inhoud, niet alleen mtime).
# Finish doet dit al met een nieuwe hook; hier opnieuw via action of docroot-endpoint
# (vangnet als de server nog een oude hook met touch() draait).
trigger_lsphp_restart() {
  local ok=0 body_ok
  # 1) Hook-action (na self-update van lib.php).
  hook_curl_soft -X POST -F "action=lsphp_restart" "$HOOK_URL"
  if [ "${HOOK_HTTP:-}" = "200" ]; then
    body_ok="$(printf '%s' "${HOOK_BODY:-}" | python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print("1" if d.get("ok") and d.get("lsphp_restart") else "0")
except Exception:
 print("0")' 2>/dev/null || echo 0)"
    if [ "$body_ok" = "1" ]; then
      ok=1
    fi
  fi
  # 2) Standalone endpoint uit public/ (gesynct door finish, ook met oude hook).
  if [ "$ok" -ne 1 ]; then
    local tmp code
    tmp="$(mktemp)"
    set +e
    code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 30 \
      -X POST -H "Authorization: Bearer ${DEPLOY_HOOK_TOKEN}" \
      -H "Accept: application/json" \
      "${BASE_URL}/deploy-lsphp-restart.php" 2>/dev/null)
    set -e
    if [ "${code:-}" = "200" ]; then
      body_ok="$(python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print("1" if d.get("ok") and d.get("lsphp_restart") else "0")
except Exception:
 print("0")' < "$tmp" 2>/dev/null || echo 0)"
      if [ "$body_ok" = "1" ]; then
        ok=1
      fi
    fi
    rm -f "$tmp"
  fi
  if [ "$ok" -eq 1 ]; then
    notice "lsphp-restart marker herschreven."
  else
    notice "lsphp-restart marker: geen bevestiging (ga door; smoke-retries volgen)."
  fi
  return 0
}

LSPHP_WAIT="${LSPHP_RESTART_WAIT_SECS:-8}"
ver="$(tr -d '[:space:]' < "${ROOT}/VERSION")"
EXPECTED_DEELPLEKKEN="$(python3 -c 'import json,sys
p="'"${ROOT}"'/data/deelplekken.json"
d=json.load(open(p))
def vis(r):
    if "visible" not in r: return True
    v=r["visible"]
    if isinstance(v,str):
        t=v.strip().lower()
        if t in ("1","true","on","yes"): return True
        if t in ("0","false","off","no",""): return False
    return bool(v)
print(sum(1 for r in (d.get("plekken") or []) if isinstance(r,dict) and vis(r)))' 2>/dev/null || echo 0)"

wait_for_footer_version() {
  local label=$1
  local max_attempts="${SMOKE_VERSION_ATTEMPTS:-6}"
  local sleep_secs="${SMOKE_VERSION_SLEEP_SECS:-10}"
  local attempt=1 code tmp
  while [ "$attempt" -le "$max_attempts" ]; do
    tmp="$(mktemp)"
    set +e
    code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 60 \
      -H 'Cache-Control: no-cache' -H 'Pragma: no-cache' \
      "${BASE_URL}/?gp_smoke=$(date +%s)")
    set -e
    echo "smoke-version ${label} http ${code} (poging ${attempt}/${max_attempts})"
    if [ "$code" = "200" ] \
      && grep -q 'Gratis planten' "$tmp" \
      && grep -q "Versie ${ver}" "$tmp"; then
      rm -f "$tmp"
      notice "Footer-versie ${ver} OK (${label})."
      return 0
    fi
    rm -f "$tmp"
    if [ "$attempt" -ge "$max_attempts" ]; then
      break
    fi
    notice "Footer-versie ${ver} nog niet zichtbaar (${label}) — lsphp-restart + ${sleep_secs}s…"
    trigger_lsphp_restart
    sleep "$sleep_secs"
    attempt=$((attempt + 1))
  done
  return 1
}

trigger_lsphp_restart
if [ "$LSPHP_WAIT" -gt 0 ] 2>/dev/null; then
  notice "Wacht ${LSPHP_WAIT}s op lsphp-recycle vóór migrate…"
  sleep "$LSPHP_WAIT"
fi

# --- migrate: primair CLI via hook (nieuwe release-map), anders /deploy/run ná verse workers ---
# Nooit migrate draaien op stale lsphp (zou oude DeelplekkenSeeder draaien).
if [ "${SKIP_MIGRATE:-}" != "1" ]; then
  migrate_ok=0

  # 1) Hook action=migrate → artisan CLI in absolute release (verse PHP, geen web-opcache).
  notice "Hook migrate (CLI in nieuwe release)…"
  hook_curl_soft -X POST \
    -F "action=migrate" \
    -F "release=${RELEASE_ID}" \
    "$HOOK_URL"
  if [ "${HOOK_HTTP:-}" = "200" ]; then
    mig_via="$(printf '%s' "${HOOK_BODY:-}" | python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print("1" if d.get("ok") and d.get("via")=="cli" else "0")
except Exception:
 print("0")' 2>/dev/null || echo 0)"
    if [ "$mig_via" = "1" ]; then
      migrate_ok=1
      notice "Hook migrate CLI → OK"
    fi
  fi

  # 2) Fallback: eerst bevestigen dat web de NIEUWE versie serveert, dan POST /deploy/run.
  if [ "$migrate_ok" -ne 1 ]; then
    notice "CLI-migrate niet beschikbaar — wacht op verse lsphp, daarna POST /deploy/run…"
    if ! wait_for_footer_version "pre-migrate"; then
      die "Kan niet migreren: footer-versie ${ver} ontbreekt (stale lsphp); seed zou op oude code draaien."
    fi

    max_attempts=5
    attempt=1
    code=""
    body=""
    while [ "$attempt" -le "$max_attempts" ]; do
      tmp="$(mktemp)"
      set +e
      code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 180 \
        -X POST -H "Authorization: Bearer ${DEPLOY_TOKEN}" \
        -H "Accept: application/json" \
        "${BASE_URL}/deploy/run")
      curl_ec=$?
      set -e
      body="$(cat "$tmp" 2>/dev/null || true)"
      rm -f "$tmp"

      if [ "$curl_ec" -eq 0 ] && [ "$code" = "200" ]; then
        run_ver="$(printf '%s' "$body" | python3 -c 'import json,sys
try:
 print(json.load(sys.stdin).get("version",""))
except Exception:
 print("")' 2>/dev/null || true)"
        if [ -n "$run_ver" ] && [ "$run_ver" != "$ver" ]; then
          notice "POST /deploy/run 200 maar version=${run_ver} ≠ ${ver} — stale worker, retry…"
          trigger_lsphp_restart
          sleep 10
          attempt=$((attempt + 1))
          continue
        fi
        notice "POST /deploy/run → 200 (version=${run_ver:-?})"
        migrate_ok=1
        break
      fi

      should_retry=0
      stale_403=0
      if [ "$curl_ec" -ne 0 ] || [ -z "$code" ] || [ "$code" = "000" ]; then
        should_retry=1
      else
        case "$code" in
          403)
            should_retry=1
            stale_403=1
            ;;
          404|5??) should_retry=1 ;;
        esac
      fi

      if [ "$should_retry" -ne 1 ] || [ "$attempt" -ge "$max_attempts" ]; then
        ok="$(printf '%s' "$body" | python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print(d.get("ok"), d.get("error",""))
except Exception:
 print("?", "non-json")' 2>/dev/null || echo '? non-json')"
        die "POST /deploy/run faalde (HTTP ${code:-conn}, ${ok})."
      fi

      if [ "$stale_403" -eq 1 ]; then
        trigger_lsphp_restart
        sleep_secs=30
        notice "POST /deploy/run HTTP 403 (mogelijk stale proces) — poging ${attempt}/${max_attempts}, opnieuw over ${sleep_secs}s…"
      else
        sleep_secs=$((attempt * 5))
        notice "POST /deploy/run HTTP ${code:-conn} — poging ${attempt}/${max_attempts}, opnieuw over ${sleep_secs}s…"
      fi
      sleep "$sleep_secs"
      attempt=$((attempt + 1))
    done
  fi

  if [ "$migrate_ok" -ne 1 ]; then
    die "Migrate/seed faalde (geen CLI-hook en geen /deploy/run)."
  fi

  # Na migrate opnieuw marker: web-workers laden verse config:cache.
  trigger_lsphp_restart
  if [ "$LSPHP_WAIT" -gt 0 ] 2>/dev/null; then
    sleep "$LSPHP_WAIT"
  fi
fi

# --- smoke: footer-versie + deelplekken-count + robots ---
if [ "${SKIP_SMOKE:-}" != "1" ]; then
  if ! wait_for_footer_version "post-migrate"; then
    die "Smoke: footer-versie ${ver} ontbreekt na retries."
  fi

  # Staging noindex (soft).
  if [ "$APP_ENV_NAME" = "staging" ]; then
    tmp="$(mktemp)"
    curl -sS -o "$tmp" --connect-timeout 15 --max-time 60 "${BASE_URL}/" || true
    if ! grep -qi 'noindex' "$tmp"; then
      notice "LET OP: geen noindex-meta in HTML."
    fi
    headers="$(mktemp)"
    curl -sSI --connect-timeout 15 --max-time 30 "${BASE_URL}/" > "$headers" || true
    if ! grep -qi '^x-robots-tag:.*noindex' "$headers"; then
      notice "LET OP: geen X-Robots-Tag: noindex"
    fi
    rm -f "$tmp" "$headers"
  fi

  # Deelplekken: verwacht ≥ aantal zichtbare rijen (visible niet false) in data/deelplekken.json, net als /api/deelplekken.
  if [ "${EXPECTED_DEELPLEKKEN:-0}" -gt 0 ] 2>/dev/null; then
    SMOKE_MAX="${SMOKE_VERSION_ATTEMPTS:-6}"
    SMOKE_SLEEP="${SMOKE_VERSION_SLEEP_SECS:-10}"
    deel_ok=0
    deel_attempt=1
    while [ "$deel_attempt" -le "$SMOKE_MAX" ]; do
      tmp="$(mktemp)"
      set +e
      code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 60 \
        -H 'Cache-Control: no-cache' \
        "${BASE_URL}/api/deelplekken?gp_smoke=$(date +%s)")
      set -e
      count="$(python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print(len(d.get("items") or []))
except Exception:
 print(0)' < "$tmp" 2>/dev/null || echo 0)"
      rm -f "$tmp"
      echo "smoke-deelplekken http ${code} count=${count} expect>=${EXPECTED_DEELPLEKKEN} (poging ${deel_attempt}/${SMOKE_MAX})"
      if [ "$code" = "200" ] && [ "$count" -ge "$EXPECTED_DEELPLEKKEN" ] 2>/dev/null; then
        deel_ok=1
        break
      fi
      if [ "$deel_attempt" -ge "$SMOKE_MAX" ]; then
        break
      fi
      notice "Deelplekken-count te laag — lsphp-restart + eventueel opnieuw seed via /deploy/run…"
      trigger_lsphp_restart
      # Één keer opnieuw seed proberen als count duidelijk te laag is (oude seeder).
      if [ "${SKIP_MIGRATE:-}" != "1" ] && [ "$deel_attempt" -eq 1 ]; then
        hook_curl_soft -X POST -F "action=migrate" -F "release=${RELEASE_ID}" "$HOOK_URL" || true
        if [ "${HOOK_HTTP:-}" != "200" ]; then
          curl -sS -o /dev/null --connect-timeout 15 --max-time 180 \
            -X POST -H "Authorization: Bearer ${DEPLOY_TOKEN}" \
            -H "Accept: application/json" \
            "${BASE_URL}/deploy/run" || true
        fi
      fi
      sleep "$SMOKE_SLEEP"
      deel_attempt=$((deel_attempt + 1))
    done
    if [ "$deel_ok" -ne 1 ]; then
      die "Smoke: deelplekken-count < ${EXPECTED_DEELPLEKKEN} na ${SMOKE_MAX} pogingen (seeder op oude release?)."
    fi
    notice "Deelplekken-count OK (>= ${EXPECTED_DEELPLEKKEN})."
  fi

  # Statische robots.txt in docroot moet de hosting-fallback overrulen.
  robots_tmp="$(mktemp)"
  set +e
  robots_code=$(curl -sS -o "$robots_tmp" -w '%{http_code}' --connect-timeout 15 --max-time 30 \
    "${BASE_URL}/robots.txt")
  set -e
  if [ "$robots_code" != "200" ]; then
    rm -f "$robots_tmp"
    die "Smoke: /robots.txt HTTP ${robots_code}, verwacht 200."
  fi
  if grep -q 'To customize, create a robots.txt' "$robots_tmp"; then
    rm -f "$robots_tmp"
    die "Smoke: /robots.txt is hosting-fallback — ontbreekt in docroot."
  fi
  if [ "$APP_ENV_NAME" = "production" ]; then
    grep -q 'Allow: /' "$robots_tmp" || { rm -f "$robots_tmp"; die "Smoke: productie-robots mist Allow: /."; }
    grep -q 'Sitemap: https://gratisplanten.nl/sitemap.xml' "$robots_tmp" \
      || { rm -f "$robots_tmp"; die "Smoke: productie-robots mist canonieke Sitemap."; }
  else
    grep -q 'Disallow: /' "$robots_tmp" || { rm -f "$robots_tmp"; die "Smoke: staging-robots mist Disallow: /."; }
  fi
  rm -f "$robots_tmp"

  notice "Smoke OK (versie ${ver}, deelplekken, robots.txt)."
fi

notice "Deploy-hook upload voltooid (artefact ${SIZE} bytes)."
# Exporteer voor workflow-summary
if [ -n "${GITHUB_OUTPUT:-}" ]; then
  echo "artifact_bytes=${SIZE}" >> "$GITHUB_OUTPUT"
  echo "extract_method=${METHOD:-unknown}" >> "$GITHUB_OUTPUT"
fi
