# Installatie (lokaal en server)

Geen secrets, hostnamen of private paden in deze repo.

## Lokaal (PHP 8.2 + SQLite)

1. `composer install`
2. `cp .env.example .env && php artisan key:generate`
3. Zet `SITE_NAME` / `APP_NAME`, `APP_URL`, database (`DB_CONNECTION=sqlite` + `database/database.sqlite`).
4. `php artisan migrate --seed` (deelplekken). Optioneel demo-aanbod: `php artisan db:seed --class=DemoSeeder`
5. `npm ci && npm run build`
6. `php artisan serve`

## Server (cPanel-achtig, docroot ≠ Laravel `public/`)

CI bouwt vendor (no-dev) en Vite-assets. Layout via secrets:

| | Staging | Production (Environment `production`) |
|---|---|---|
| Docroot | `STAGING_DEPLOY_PATH` | `DEPLOY_PATH` |
| App (buiten webroot) | `STAGING_APP_DIR` (default: sibling `…/stekjes-staging-app`) | `APP_DIR` (verplicht) |

Deploy-stappen:

1. Rsync **app zonder `public/`** → app-map.
2. Rsync **inhoud van `public/`** → docroot (`--delete`, excludes: `.well-known/`, `cgi-bin/`).
3. Schrijf `index.php` in de docroot die `vendor/autoload.php` en `bootstrap/app.php` uit de app-map laadt, en `$app->usePublicPath(__DIR__)` zet zodat `asset()` / Vite-manifest de docroot gebruiken.
4. `.env` in de app-map uit secrets. `APP_KEY`: uit secret, anders eenmalig op de server genereren en bewaren — nooit committen.
5. `php artisan migrate --force`, daarna `php artisan db:seed --class=DeelplekkenSeeder --force` (idempotent upsert), plus config/route/view cache (in de app-map).
6. Staging: `APP_ENV=staging` / `NOINDEX=true`. Productie: indexeerbaar.

SSH/host: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` (production Environment overschrijft repo-secrets met dezelfde naam).

## Branches

Feature-PR’s → `staging`. Productie via tag `v*.*.*` op `master`. PR’s naar `master` alleen vanaf `staging`.
