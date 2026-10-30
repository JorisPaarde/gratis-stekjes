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

| | Staging (cPanel) | Production (Environment `production`) |
|---|---|---|
| Docroot | `STAGING_DEPLOY_PATH` (subdomein-map zelf, niet `…/public`) | `DEPLOY_PATH` / later `public_html` |
| App (buiten webroot) | `STAGING_APP_DIR` (bijv. `…/app-staging`) | `APP_DIR` / later `…/app-prod` |
| Git-clone op server | `CPANEL_REPO_ROOT` → branch `deploy-staging` | later `repo-prod` → `master` |

**Staging cPanel-stappen** (`deploy-staging-cpanel.yml` + `scripts/cpanel-deploy.sh`):

1. Build in CI; force-push artefact naar GitHub-branch `deploy-staging` (inclusief `.cpanel.yml` — **niet** op `staging`/`master`).
2. UAPI `VersionControl::update` + `VersionControlDeployment::create` (`.cpanel.yml` rsynct app → `APP_DIR`, `public/` → docroot; behoud `.env`/storage/uploads).
3. `index.php` in docroot met relatief pad (`../app-staging`) + `usePublicPath(__DIR__)`.
4. `.env` via Fileman UAPI; `APP_KEY` behouden indien aanwezig.
5. `POST /deploy/run` (Bearer `DEPLOY_TOKEN`) → migrate + DeelplekkenSeeder + caches.
6. `APP_ENV=staging` / `NOINDEX=true`.

Oude SSH-deploy blijft tot `STAGING_USE_CPANEL=1`. Productie-SSH: `DEPLOY_HOST`/`USER`/`PORT`/`SSH_KEY`/`KNOWN_HOSTS`.

## Branches

Feature-PR’s → `staging`. Productie via tag `v*.*.*` op `master`. PR’s naar `master` alleen vanaf `staging`.
