#!/usr/bin/env bash
# Self-test voor scripts/deploy-hook-http.sh: retry bij tijdelijke storingen, direct falen bij echte fouten.
# Draait lokaal tegen een mini-server (python3) die per scenario een vaste rij antwoorden geeft.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
PORT="${TEST_PORT:-18765}"
QUEUE="${WORK}/queue"
HITS="${WORK}/hits"
trap '[ "${SRV:-0}" -gt 0 ] && kill "$SRV" 2>/dev/null; rm -rf "$WORK"' EXIT

cat > "${WORK}/server.py" <<'PY'
import http.server, sys
queue, hits, port = sys.argv[1], sys.argv[2], int(sys.argv[3])
class H(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        n = int(self.headers.get('Content-Length') or 0)
        if n: self.rfile.read(n)
        with open(hits, 'a') as f: f.write('x\n')
        lines = open(queue).read().splitlines()
        if lines:
            line, rest = lines[0], lines[1:]
            open(queue, 'w').write('\n'.join(rest) + ('\n' if rest else ''))
        else:
            line = '200 {"ok":true}'
        status, body = line.split(' ', 1)
        data = body.encode()
        self.send_response(int(status))
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)
    def log_message(self, *a): pass
http.server.HTTPServer(('127.0.0.1', port), H).serve_forever()
PY
: > "$QUEUE"; : > "$HITS"
python3 "${WORK}/server.py" "$QUEUE" "$HITS" "$PORT" &
SRV=$!
for _ in $(seq 1 50); do
  curl -s -o /dev/null "http://127.0.0.1:${PORT}/" -X POST && break
  sleep 0.1
done

# Stubs voor wat de aanroeper normaal levert.
die() { echo "DIE: $*" >&2; exit 1; }
notice() { echo "$*"; }
error_ann() { echo "$*" >&2; }
print_install_hint() { echo "INSTALL-HINT" >&2; }
DEPLOY_HOOK_TOKEN="test-token-niet-geheim"
BASE_URL="http://127.0.0.1:${PORT}"
HOOK_PATH="/deploy-hook.php"
export HOOK_RETRY_SLEEPS="0 0 0"
# shellcheck source=scripts/deploy-hook-http.sh
source "${ROOT}/scripts/deploy-hook-http.sh"

fails=0
# scenario <naam> <idempotent> <verwachte http> <verwachte pogingen> <antwoord-regels…>
scenario() {
  local name=$1 idem=$2 want_http=$3 want_attempts=$4
  shift 4
  : > "$HITS"
  printf '%s\n' "$@" > "$QUEUE"
  [ "$#" -eq 0 ] && : > "$QUEUE"
  hook_call_retry "$name" "$idem" -X POST -F "action=init" "${BASE_URL}${HOOK_PATH}" >/dev/null 2>&1 || true
  local hits
  hits=$(wc -l < "$HITS" | tr -d ' ')
  if [ "$HOOK_HTTP" = "$want_http" ] && [ "$HOOK_ATTEMPTS" = "$want_attempts" ] && [ "$hits" = "$want_attempts" ]; then
    echo "ok   ${name}: HTTP ${HOOK_HTTP} na ${HOOK_ATTEMPTS} poging(en)"
  else
    echo "FAIL ${name}: HTTP ${HOOK_HTTP} (verwacht ${want_http}), pogingen ${HOOK_ATTEMPTS} (verwacht ${want_attempts}), server-hits ${hits}"
    fails=$((fails + 1))
  fi
}

IMUNIFY='403 {"message":"Access denied by Imunify360 bot-protection. IPs used for automation should be whitelisted"}'

scenario "imunify-403 ×2 daarna ok"        1 200 3 "$IMUNIFY" "$IMUNIFY"
scenario "imunify-403 blijft (geeft op)"    1 403 4 "$IMUNIFY" "$IMUNIFY" "$IMUNIFY" "$IMUNIFY" "$IMUNIFY"
scenario "429 rate_limited daarna ok"       1 200 2 '429 {"ok":false,"error":"rate_limited"}'
scenario "echte token-fout: geen retry"     1 403 1 '403 {"ok":false,"error":"forbidden"}'
scenario "https_required: geen retry"       1 403 1 '403 {"ok":false,"error":"https_required"}'
scenario "400 bad_chunks: geen retry"       1 400 1 '400 {"ok":false,"error":"bad_chunks"}'
scenario "502 proxy (idempotent) daarna ok" 1 200 2 '502 <html>Bad Gateway</html>'
scenario "502 proxy (niet idempotent)"      0 502 1 '502 <html>Bad Gateway</html>'
scenario "500 server_error (idempotent)"    1 200 2 '500 {"ok":false,"error":"server_error"}'
scenario "500 met hook-fout: geen retry"    1 500 1 '500 {"ok":false,"error":"extract_failed"}'
scenario "finish: WAF-403 wel retry"        0 200 2 "$IMUNIFY"
scenario "200 direct"                       1 200 1

# Connectiefout: poort zonder server.
kill "$SRV" 2>/dev/null || true
wait "$SRV" 2>/dev/null || true
SRV=0
HOOK_HTTP=""
HOOK_ATTEMPTS=0
hook_call_retry conn 1 -X POST "${BASE_URL}${HOOK_PATH}" >/dev/null 2>&1 || true
if [ "$HOOK_HTTP" = "000" ] && [ "$HOOK_ATTEMPTS" = "4" ]; then
  echo "ok   connectiefout (idempotent): 4 pogingen, dan HTTP 000"
else
  echo "FAIL connectiefout (idempotent): HTTP ${HOOK_HTTP}, pogingen ${HOOK_ATTEMPTS}"; fails=$((fails + 1))
fi
if (hook_call_retry conn 0 -X POST "${BASE_URL}${HOOK_PATH}" >/dev/null 2>&1); then
  echo "FAIL connectiefout (niet idempotent) moet direct falen"; fails=$((fails + 1))
else
  echo "ok   connectiefout (niet idempotent): direct gefaald"
fi

if [ "$fails" -gt 0 ]; then
  echo "${fails} scenario(s) gefaald" >&2
  exit 1
fi
echo "Alle scenario's OK."
