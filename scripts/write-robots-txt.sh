#!/usr/bin/env bash
# Schrijf env-aware public/robots.txt (zelfde inhoud als PageController::robotsBody).
# Gebruik: APP_ENV_NAME=staging|production ./scripts/write-robots-txt.sh [pad]
# Default pad: <repo>/public/robots.txt
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="${1:-${ROOT}/public/robots.txt}"
APP_ENV_NAME="${APP_ENV_NAME:-staging}"

mkdir -p "$(dirname "$DEST")"

if [ "$APP_ENV_NAME" = "production" ]; then
  # Canonieke productie-sitemap (gelijk aan PageController bij APP_URL=https://gratisplanten.nl).
  printf '%s\n' \
    'User-agent: *' \
    'Allow: /' \
    'Sitemap: https://gratisplanten.nl/sitemap.xml' \
    > "$DEST"
else
  printf '%s\n' \
    'User-agent: *' \
    'Disallow: /' \
    > "$DEST"
fi

test -s "$DEST" || { echo "write-robots-txt: leeg bestand: ${DEST}" >&2; exit 1; }
echo "robots.txt → ${DEST} (${APP_ENV_NAME})"
