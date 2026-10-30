#!/usr/bin/env bash
# Genereer deploy-hook token, zet GitHub secret, print ALLEEN config met hash.
# Gebruik: ./scripts/make-deploy-hook-config.sh staging|production
# Vereist: gh (ingelogd), php, openssl.
# Het plaintext token wordt NOOIT geprint (alleen via gh secret set / pipe).
set -euo pipefail

ENV_NAME="${1:-}"
case "$ENV_NAME" in
  staging)
    APP_DIR="/home/gratisplanten/app-staging"
    DOCROOT="/home/gratisplanten/staging.gratisplanten.nl"
    GH_ENV="staging"
    PHP_ENV="staging"
    ;;
  production|prod)
    ENV_NAME="production"
    APP_DIR="/home/gratisplanten/app-prod"
    DOCROOT="/home/gratisplanten/public_html"
    GH_ENV="production"
    PHP_ENV="production"
    ;;
  *)
    printf '%s\n' "Gebruik: $0 staging|production" >&2
    exit 1
    ;;
esac

if ! command -v gh >/dev/null 2>&1; then
  printf '%s\n' "gh CLI ontbreekt. Installeer GitHub CLI en auth status." >&2
  exit 1
fi
if ! command -v php >/dev/null 2>&1; then
  printf '%s\n' "php ontbreekt (nodig voor password_hash)." >&2
  exit 1
fi

# Random token (32 bytes hex = 64 chars). Nooit echo'en.
TOKEN="$(openssl rand -hex 32)"

# Mask in Actions-logs als dit ooit daar draait.
if [ -n "${GITHUB_ACTIONS:-}" ]; then
  printf '%s\n' "::add-mask::${TOKEN}"
fi

# Hash (password_hash). Token via env naar php — niet als argv (ps-zichtbaar).
TOKEN_HASH="$(
  DEPLOY_HOOK_TOKEN_PLAIN="$TOKEN" php -r 'echo password_hash(getenv("DEPLOY_HOOK_TOKEN_PLAIN"), PASSWORD_DEFAULT);'
)"

# Zet secret via stdin — token komt niet in argv van gh.
printf '%s' "$TOKEN" | gh secret set DEPLOY_HOOK_TOKEN --env "$GH_ENV"

# Wis plaintext zo snel mogelijk.
TOKEN=""
unset TOKEN

printf '%s\n' "Secret DEPLOY_HOOK_TOKEN gezet in GitHub environment '${GH_ENV}'." >&2
printf '%s\n' "Plak onderstaande inhoud in cPanel File Manager als:" >&2
printf '%s\n' "  /home/gratisplanten/.deploy/${PHP_ENV}.php  (mode 0600; map .deploy mode 0700)" >&2
printf '%s\n' "---" >&2

# Alleen config naar stdout (voor redirect/copy). Geen token.
cat <<EOF
<?php

declare(strict_types=1);

return [
    'token_hash' => '${TOKEN_HASH}',
    'app_dir' => '${APP_DIR}',
    'docroot' => '${DOCROOT}',
    'env' => '${PHP_ENV}',
];
EOF
