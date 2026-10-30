# Installatie (lokaal en server)

Geen secrets, hostnamen of private paden in deze repo.

## Lokaal (PHP 8.2 + SQLite)

1. `composer install`
2. `cp .env.example .env && php artisan key:generate`
3. Zet `SITE_NAME` / `APP_NAME`, `APP_URL`, database (`DB_CONNECTION=sqlite` + `database/database.sqlite`).
4. `php artisan migrate --seed` (deelplekken). Optioneel demo-aanbod: `php artisan db:seed --class=DemoSeeder`
5. `npm ci && npm run build`
6. `php artisan serve`

## Server (cPanel: docroot ≠ Laravel-`public/`-map in de repo)

CI bouwt vendor (no-dev) en Vite-assets. Deploy via HTTPS-hook (geen shell, geen `:2083`):

| | Staging | Production (later) |
|---|---|---|
| Docroot | `…/staging.gratisplanten.nl` | `…/public_html` |
| App (buiten webroot) | `…/app-staging` | `…/app-prod` |
| Hook-config | `…/.deploy/staging.php` | `…/.deploy/production.php` |
| Workflow | `deploy-staging-cpanel.yml` | `deploy-prod-hook.yml` (tags) |

**Staging:** build → zip → chunked upload naar `/deploy-hook.php` → **`POST /deploy/run`** → smoke. Eenmalige File Manager-stappen: [`deploy/hook/INSTALL.md`](../deploy/hook/INSTALL.md). Secrets: `AGENTS.md`. Oude SSH- en UAPI-routes zijn retired/legacy.

## Branches

Feature-PR’s → `staging`. Productie via tag `v*.*.*` op `master`. PR’s naar `master` alleen vanaf `staging`.
