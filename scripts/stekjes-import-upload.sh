#!/usr/bin/env bash
# Chunked upload van een stekjes-export.zip naar prod POST /deploy/import*.
# Logt ALLEEN tabelnamen + tellingen (geen PII, geen row-data).
#
# Vereiste env:
#   DEPLOY_TOKEN, BASE_URL, PACKAGE_PATH
# Optioneel: DRY_RUN=1, FORCE=1, CHUNK_SIZE (default 5 MiB)
set -euo pipefail

CHUNK_SIZE="${CHUNK_SIZE:-5242880}"

die() { printf '%s\n' "$*" >&2; exit 1; }
notice() {
  if [ -n "${GITHUB_ACTIONS:-}" ]; then printf '%s\n' "::notice::$*"; else printf '%s\n' "$*"; fi
}
error_ann() {
  if [ -n "${GITHUB_ACTIONS:-}" ]; then printf '%s\n' "::error::$*"; else printf '%s\n' "$*" >&2; fi
}

mask() { [ -n "${1-}" ] && printf '%s\n' "::add-mask::${1}"; }

mask "${DEPLOY_TOKEN:-}"

[ -n "${DEPLOY_TOKEN:-}" ] || die "DEPLOY_TOKEN ontbreekt."
[ -n "${BASE_URL:-}" ] || die "BASE_URL ontbreekt."
[ -n "${PACKAGE_PATH:-}" ] || die "PACKAGE_PATH ontbreekt."
[ -f "$PACKAGE_PATH" ] || die "PACKAGE_PATH bestaat niet."

BASE_URL="${BASE_URL%/}"
SIZE="$(wc -c < "$PACKAGE_PATH" | tr -d ' ')"
TOTAL_SHA="$(sha256sum "$PACKAGE_PATH" | awk '{print $1}')"
notice "Package bytes=${SIZE} sha256=${TOTAL_SHA:0:12}…"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT
CHUNK_DIR="${WORKDIR}/chunks"
mkdir -p "$CHUNK_DIR"
split -b "$CHUNK_SIZE" -d -a 5 "$PACKAGE_PATH" "${CHUNK_DIR}/chunk_"
mapfile -t CHUNKS < <(ls -1 "$CHUNK_DIR"/chunk_* | sort)
CHUNK_COUNT="${#CHUNKS[@]}"
notice "Chunks=${CHUNK_COUNT}"

api_curl() {
  local tmp http
  tmp="$(mktemp)"
  set +e
  http=$(curl -sS -o "$tmp" -w '%{http_code}' --connect-timeout 20 --max-time 300 \
    -H "Authorization: Bearer ${DEPLOY_TOKEN}" \
    -H "Accept: application/json" \
    "$@" 2>/dev/null)
  local ec=$?
  set -e
  API_HTTP="$http"
  API_BODY="$(cat "$tmp" 2>/dev/null || true)"
  rm -f "$tmp"
  if [ "$ec" -ne 0 ] || [ -z "$API_HTTP" ] || [ "$API_HTTP" = "000" ]; then
    die "curl naar deploy/import faalde (connectie)."
  fi
}

json_field() {
  local key=$1
  printf '%s' "$API_BODY" | python3 -c "import json,sys
try:
 d=json.load(sys.stdin); v=d.get('${key}',''); print(v if not isinstance(v, (dict,list)) else json.dumps(v,separators=(',',':')))
except Exception:
 print('')" 2>/dev/null || true
}

# --- init ---
api_curl -X POST \
  -F "total_size=${SIZE}" \
  -F "total_sha256=${TOTAL_SHA}" \
  -F "chunk_count=${CHUNK_COUNT}" \
  "${BASE_URL}/deploy/import/init"

if [ "$API_HTTP" != "200" ]; then
  error_ann "import/init HTTP ${API_HTTP} error=$(json_field error)"
  exit 1
fi
UPLOAD_ID="$(json_field upload_id)"
[ -n "$UPLOAD_ID" ] || die "Geen upload_id."
notice "upload_id gezet (niet gelogd volledig)."

# --- chunks ---
idx=0
for chunk_path in "${CHUNKS[@]}"; do
  csha="$(sha256sum "$chunk_path" | awk '{print $1}')"
  api_curl -X POST \
    -F "upload_id=${UPLOAD_ID}" \
    -F "chunk_index=${idx}" \
    -F "chunk_sha256=${csha}" \
    -F "chunk=@${chunk_path};type=application/octet-stream" \
    "${BASE_URL}/deploy/import/chunk"
  if [ "$API_HTTP" != "200" ]; then
    error_ann "import/chunk ${idx} HTTP ${API_HTTP} error=$(json_field error)"
    exit 1
  fi
  idx=$((idx + 1))
done
notice "Alle chunks geüpload."

# --- import ---
DRY_FLAG=false
FORCE_FLAG=false
[ "${DRY_RUN:-}" = "1" ] || [ "${DRY_RUN:-}" = "true" ] && DRY_FLAG=true
[ "${FORCE:-}" = "1" ] || [ "${FORCE:-}" = "true" ] && FORCE_FLAG=true

api_curl -X POST \
  -F "upload_id=${UPLOAD_ID}" \
  -F "dry_run=${DRY_FLAG}" \
  -F "force=${FORCE_FLAG}" \
  "${BASE_URL}/deploy/import"

if [ "$API_HTTP" != "200" ]; then
  error_ann "import HTTP ${API_HTTP} error=$(json_field error)"
  # Counts + veilige diagnose (SQLSTATE/tabel/kolom); nooit waarden/PII.
  printf '%s' "$API_BODY" | python3 -c '
import json,sys
try:
 d=json.load(sys.stdin)
 for k,v in (d.get("counts") or {}).items():
  print(f"count.{k}={v}")
 for k,v in (d.get("dropped_columns") or {}).items():
  if v: print(f"dropped.{k}="+",".join(v))
 mt=d.get("missing_tables") or []
 if mt: print("missing_tables="+",".join(mt))
 diag=d.get("diagnosis") or {}
 for k in ("sqlstate","driver_code","table","column","error_class"):
  if diag.get(k) is not None and diag.get(k) != "":
   print(f"diagnosis.{k}={diag[k]}")
except Exception:
 pass
' || true
  exit 1
fi

notice "Import response OK (dry_run=${DRY_FLAG})"
printf '%s' "$API_BODY" | python3 -c '
import json,sys
d=json.load(sys.stdin)
print("ok="+str(d.get("ok")))
print("dry_run="+str(d.get("dry_run")))
print("already_imported="+str(bool(d.get("already_imported"))))
keys=("prod_voor","bron","toegevoegd","samengevoegd","overgeslagen","al_aanwezig","prod_na","sum_voor","sum_na")
for table,stats in sorted((d.get("tables") or {}).items()):
  if not isinstance(stats, dict):
    continue
  for k in keys:
    if k in stats:
      print(f"{table}.{k}={stats[k]}")
for k,v in sorted((d.get("dropped_columns") or {}).items()):
 if v: print(f"dropped.{k}="+",".join(v))
mt=d.get("missing_tables") or []
if mt: print("missing_tables="+",".join(mt))
fr=d.get("files_restored")
if fr is not None: print(f"files_restored={fr}")
'
