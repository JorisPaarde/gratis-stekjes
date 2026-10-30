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
mask "${CRON_TOKEN:-}"
mask "${APP_KEY:-}"

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

  ARTIFACT="${WORKDIR}/release-${RELEASE_ID}.zip"
  (
    cd "$STAGE"
    zip -qr "$ARTIFACT" .
  )
fi

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
  # APP_KEY: alleen als secret gezet; anders laat hook bestaande/genereer.
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
  dotenv_line DEPLOY_TOKEN "$DEPLOY_TOKEN"
  dotenv_line DEPLOY_ENDPOINT_ENABLED "$DEPLOY_ENDPOINT_ENABLED_VAL"
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

# --- migrate via bestaand endpoint ---
if [ "${SKIP_MIGRATE:-}" != "1" ]; then
  tmp="$(mktemp)"
  set +e
  code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 180 \
    -X POST -H "Authorization: Bearer ${DEPLOY_TOKEN}" \
    -H "Accept: application/json" \
    "${BASE_URL}/deploy/run")
  set -e
  body="$(cat "$tmp" 2>/dev/null || true)"
  rm -f "$tmp"
  if [ "$code" != "200" ]; then
    ok="$(printf '%s' "$body" | python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print(d.get("ok"), d.get("error",""))
except Exception:
 print("?", "non-json")' 2>/dev/null || echo '? non-json')"
    die "POST /deploy/run faalde (HTTP ${code}, ${ok})."
  fi
  notice "POST /deploy/run → 200"
fi

# --- smoke ---
if [ "${SKIP_SMOKE:-}" != "1" ]; then
  ver="$(tr -d '[:space:]' < "${ROOT}/VERSION")"
  tmp="$(mktemp)"
  set +e
  code=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 15 --max-time 60 "${BASE_URL}/")
  set -e
  echo "smoke http ${code}"
  if [ "$code" != "200" ]; then
    rm -f "$tmp"
    die "Smoke: homepage HTTP ${code}, verwacht 200."
  fi
  grep -q 'Gratis planten' "$tmp" || { rm -f "$tmp"; die "Smoke: merknaam ontbreekt."; }
  grep -q "Versie ${ver}" "$tmp" || { rm -f "$tmp"; die "Smoke: footer-versie ${ver} ontbreekt."; }
  if [ "$APP_ENV_NAME" = "staging" ]; then
    if ! grep -qi 'noindex' "$tmp"; then
      notice "LET OP: geen noindex-meta in HTML."
    fi
    headers="$(mktemp)"
    curl -sSI --connect-timeout 15 --max-time 30 "${BASE_URL}/" > "$headers" || true
    if ! grep -qi '^x-robots-tag:.*noindex' "$headers"; then
      notice "LET OP: geen X-Robots-Tag: noindex"
    fi
    rm -f "$headers"
  fi
  rm -f "$tmp"
  notice "Smoke OK (versie ${ver})."
fi

notice "Deploy-hook upload voltooid (artefact ${SIZE} bytes)."
# Exporteer voor workflow-summary
if [ -n "${GITHUB_OUTPUT:-}" ]; then
  echo "artifact_bytes=${SIZE}" >> "$GITHUB_OUTPUT"
  echo "extract_method=${METHOD:-unknown}" >> "$GITHUB_OUTPUT"
fi
