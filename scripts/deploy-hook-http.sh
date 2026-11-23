#!/usr/bin/env bash
# HTTP-helpers voor scripts/deploy-hook-upload.sh (en de self-test): één hook-call, plus
# een wrapper die tijdelijke storingen opnieuw probeert (Imunify-challenge, proxy-5xx, time-outs).
#
# Verwacht uit de aanroeper: DEPLOY_HOOK_TOKEN, BASE_URL, HOOK_PATH en de functies
# die(), notice(), error_ann(), print_install_hint().
#
# Tijdelijk (opnieuw proberen):
#   - 403/429 waarbij de body geen hook-foutcode heeft (WAF/Imunify-bot-protection; de hook
#     zelf antwoordt altijd met error=...), of error=rate_limited/server_error.
#   - 5xx zonder hook-foutcode (gateway/proxy), alleen als de aanroep idempotent is.
#   - curl-fout / HTTP 000 (connection reset, time-out, DNS), alleen als idempotent.
# Echt (direct falen, geen herhaling): elke andere HTTP-status of hook-foutcode
# (bijv. forbidden, https_required, bad_chunks, chunk_checksum), en 404 (hook niet geïnstalleerd).
#
# Instelbaar (default: 6 pogingen, wachttijden 20 40 60 60 60 s):
#   HOOK_RETRY_SLEEPS="20 40 60 60 60"   (het aantal getallen + 1 = aantal pogingen)

HOOK_RETRY_SLEEPS="${HOOK_RETRY_SLEEPS:-20 40 60 60 60}"

warn_ann() {
  if [ -n "${GITHUB_ACTIONS:-}" ]; then
    printf '%s\n' "::warning::$*"
  else
    printf '%s\n' "$*" >&2
  fi
}

# hook_curl curl-args…  → zet HOOK_HTTP + HOOK_BODY (+ HOOK_CURL_EC).
# Met HOOK_CONN_SOFT=1 geeft een connectiefout HOOK_HTTP=000 in plaats van die().
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
  HOOK_CURL_EC="$ec"
  HOOK_HTTP="$http"
  HOOK_BODY="$(cat "$tmp" 2>/dev/null || true)"
  rm -f "$tmp"
  if [ "$ec" -ne 0 ] || [ -z "$HOOK_HTTP" ] || [ "$HOOK_HTTP" = "000" ]; then
    if [ "${HOOK_CONN_SOFT:-0}" = "1" ]; then
      HOOK_HTTP="000"
      return 0
    fi
    die "curl naar deploy-hook faalde (connectie, curl-exit ${ec})."
  fi
  if [ "$HOOK_HTTP" = "404" ]; then
    print_install_hint
    exit 1
  fi
}

# Hook-foutcode uit HOOK_BODY: de "error"-waarde, "?" (JSON zonder error) of "non-json".
hook_error_code() {
  printf '%s' "${HOOK_BODY:-}" | python3 -c 'import json,sys
try:
 d=json.load(sys.stdin); print(d.get("error","?") if isinstance(d,dict) else "?")
except Exception:
 print("non-json")' 2>/dev/null || echo non-json
}

# hook_is_transient <idempotent 0|1>  → exit 0 als HOOK_HTTP/HOOK_BODY een tijdelijke storing is.
hook_is_transient() {
  local idempotent=$1 code
  if [ "$HOOK_HTTP" = "000" ]; then
    [ "$idempotent" = "1" ]
    return
  fi
  code="$(hook_error_code)"
  case "$HOOK_HTTP" in
    403|429)
      case "$code" in '?'|non-json|rate_limited) return 0 ;; esac
      ;;
    5??)
      if [ "$idempotent" = "1" ]; then
        case "$code" in '?'|non-json|server_error) return 0 ;; esac
      fi
      ;;
  esac
  return 1
}

# hook_call_retry <label> <idempotent 0|1> curl-args…
# Doet de call opnieuw bij tijdelijke storingen (zie boven). Geeft altijd 0 terug; de aanroeper
# controleert HOOK_HTTP. Zet HOOK_ATTEMPTS = aantal gedane pogingen.
hook_call_retry() {
  local label=$1 idempotent=$2
  shift 2
  local sleeps=($HOOK_RETRY_SLEEPS) attempt=0 max delay code
  max=$(( ${#sleeps[@]} + 1 ))
  while :; do
    attempt=$((attempt + 1))
    HOOK_ATTEMPTS=$attempt
    HOOK_CONN_SOFT="$idempotent"
    hook_curl "$@"
    HOOK_CONN_SOFT=0
    [ "$HOOK_HTTP" = "200" ] && return 0
    if ! hook_is_transient "$idempotent"; then
      return 0
    fi
    code="$(hook_error_code)"
    if [ "$attempt" -ge "$max" ]; then
      warn_ann "Hook ${label}: tijdelijke storing (HTTP ${HOOK_HTTP}, ${code}) na ${attempt} pogingen, ik geef op."
      return 0
    fi
    delay="${sleeps[$((attempt - 1))]}"
    warn_ann "Hook ${label}: tijdelijke storing (HTTP ${HOOK_HTTP}, ${code}, curl-exit ${HOOK_CURL_EC:-0}), poging ${attempt}/${max}, opnieuw over ${delay}s."
    # Serverantwoord (geen secrets) kort loggen zodat een blokkade herkenbaar is.
    echo "Antwoord: $(printf '%s' "$HOOK_BODY" | tr -d '\r' | tr '\n' ' ' | cut -c1-200)"
    sleep "$delay"
  done
}
