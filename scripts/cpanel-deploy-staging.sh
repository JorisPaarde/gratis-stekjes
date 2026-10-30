#!/usr/bin/env bash
# cPanel Git Version Control deploy-task voor staging/prod-achtig layout.
# Verwacht env: APP_DIR (Laravel buiten webroot), DOCROOT (subdomein/main docroot).
# cwd = cPanel-repo-clone. Overschrijft nooit .env of storage-inhoud op de server.
# Docroot: geen overwrite van .env, storage(/), uploads/, lege public/.
# Artisan mag falen → HTTP POST /deploy/run is de betrouwbare fallback.
set -euo pipefail

if [ -z "${APP_DIR:-}" ] || [ -z "${DOCROOT:-}" ]; then
  printf '%s\n' 'cpanel-deploy-staging: APP_DIR en DOCROOT zijn verplicht' >&2
  exit 1
fi

REPO_ROOT="$(pwd)"
PHP_BIN=""
for candidate in /usr/local/bin/ea-php82 /usr/local/bin/ea-php81 /usr/local/bin/php /usr/bin/php; do
  if [ -x "$candidate" ]; then
    PHP_BIN="$candidate"
    break
  fi
done

mkdir -p \
  "${APP_DIR}/storage/framework/cache" \
  "${APP_DIR}/storage/framework/sessions" \
  "${APP_DIR}/storage/framework/views" \
  "${APP_DIR}/storage/logs" \
  "${APP_DIR}/storage/backups" \
  "${APP_DIR}/storage/app/public" \
  "${APP_DIR}/bootstrap/cache" \
  "${DOCROOT}/.well-known/acme-challenge"

chmod 700 "${APP_DIR}/storage/backups" 2>/dev/null || true

# Relatief pad docroot → app (bijv. ../app-staging of ../app-prod).
if APP_REL="$(realpath --relative-to="${DOCROOT}" "${APP_DIR}" 2>/dev/null)" && [ -n "${APP_REL}" ]; then
  :
else
  APP_REL="../$(basename "${APP_DIR}")"
fi

# App zonder public/ en zonder server-state (.env + storage-inhoud blijven).
/usr/bin/rsync -a --delete \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.cpanel.yml' \
  --exclude='node_modules/' \
  --exclude='tests/' \
  --exclude='public/' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='storage/logs/' \
  --exclude='storage/framework/cache/' \
  --exclude='storage/framework/sessions/' \
  --exclude='storage/framework/views/' \
  --exclude='storage/app/' \
  --exclude='storage/backups/' \
  "${REPO_ROOT}/" "${APP_DIR}/"

# Inhoud public/ → docroot. Bewaar ACME, cgi-bin, .env, storage(-symlink), uploads, lege public/.
/usr/bin/rsync -a --delete \
  --exclude='.well-known/' \
  --exclude='cgi-bin/' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='storage' \
  --exclude='storage/' \
  --exclude='uploads' \
  --exclude='uploads/' \
  --exclude='public' \
  --exclude='public/' \
  "${REPO_ROOT}/public/" "${DOCROOT}/"

# index.php: relatieve paden naar de app-map (werkt voor app-staging én later app-prod).
cat > "${DOCROOT}/index.php" <<EOF
<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/${APP_REL}/vendor/autoload.php';
\$app = require_once __DIR__ . '/${APP_REL}/bootstrap/app.php';
\$app->usePublicPath(__DIR__);
\$app->handleRequest(Illuminate\\Http\\Request::capture());
EOF

chmod -R ug+rwx "${APP_DIR}/storage/framework" "${APP_DIR}/storage/logs" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true

# noshell: artisan hier is optioneel en mag falen. Betrouwbare route = POST /deploy/run.
if [ -n "$PHP_BIN" ] && [ -f "${APP_DIR}/artisan" ] && [ -f "${APP_DIR}/.env" ]; then
  set +e
  (
    cd "${APP_DIR}"
    "$PHP_BIN" artisan migrate --force \
      && "$PHP_BIN" artisan db:seed --class=DeelplekkenSeeder --force \
      && "$PHP_BIN" artisan config:clear \
      && "$PHP_BIN" artisan config:cache \
      && "$PHP_BIN" artisan route:cache \
      && "$PHP_BIN" artisan view:cache
  )
  printf '%s\n' "cpanel-deploy-staging: artisan exit=$? (negeer bij noshell; /deploy/run is leidend)"
  set -e
else
  printf '%s\n' 'cpanel-deploy-staging: geen PHP-CLI/.env — verwacht POST /deploy/run voor migrate/cache'
fi

printf '%s\n' "cpanel-deploy-staging: klaar (APP_REL=${APP_REL})"
