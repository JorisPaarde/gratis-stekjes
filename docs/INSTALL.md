# Installatie (lokaal en server)

Geen secrets, hostnamen of private paden in deze repo.

## Lokaal (PHP 8.2 + SQLite)

1. `composer install`
2. `cp .env.example .env && php artisan key:generate`
3. Zet `SITE_NAME` / `APP_NAME`, `APP_URL`, database (`DB_CONNECTION=sqlite` + `database/database.sqlite`).
4. `php artisan migrate`
5. `npm ci && npm run build`
6. `php artisan serve`

## Server

1. CI bouwt vendor (no-dev) en Vite-assets.
2. Private app-map **per environment** (staging ≠ productie). Optioneel production-secret `APP_DIR`; anders sibling van de docroot, afgeleid op de server.
3. `public/` → docroot; `index.php` laadt de private app. Later mag de docroot ook rechtstreeks op `public/` wijzen.
4. `.env` uit secrets (`STAGING_*` op staging; op production Environment o.a. `DB_*`, `BASE_URL`, `SITE_NAME`, `SMTP_*`, `CRON_TOKEN`). `APP_KEY`: uit secret, anders eenmalig genereren op de server en bewaren — nooit committen.
5. `php artisan migrate --force` en config/route/view cache.
6. Rsync `--delete` excludes: `.well-known/`, `cgi-bin/`, private app-mappen. Staging: noindex.

## Branches

Feature-PR’s → `staging`. Productie via tag `v*.*.*` op `master`. PR’s naar `master` alleen vanaf `staging`.
