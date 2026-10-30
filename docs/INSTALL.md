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

CI bouwt vendor (no-dev) en Vite-assets. Layout via secrets (zie ook `AGENTS.md`):

| | Staging (cPanel, noshell) | Production (Environment `production`) |
|---|---|---|
| Docroot | vaste map `…/staging.gratisplanten.nl` | `DEPLOY_PATH` / later `public_html` |
| App (buiten webroot) | vaste map `…/app-staging` | `APP_DIR` / later `…/app-prod` |
| Git-clone op server | `…/repo-staging` → branch `deploy-staging` | later `repo-prod` → `master` |

**Staging** (`deploy-staging-cpanel.yml` + `scripts/cpanel-deploy.sh`): build → force-push `deploy-staging` → UAPI deploy (bestanden) → Fileman `.env` → **`POST /deploy/run`** (migrate/cache; artisan in `.cpanel.yml` is onbetrouwbaar zonder shell). Secrets: `AGENTS.md`. Oude SSH-workflow is retired. Productie-SSH: `DEPLOY_*`.

## Branches

Feature-PR’s → `staging`. Productie via tag `v*.*.*` op `master`. PR’s naar `master` alleen vanaf `staging`.
