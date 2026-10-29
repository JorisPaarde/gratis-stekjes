# Meewerken

## Branchflow

| Branch | Rol |
|--------|-----|
| `staging` | Stagingomgeving. Hier landen feature-PR’s. |
| `master` | Productie. Alleen via PR **vanaf `staging`**. |

- Feature-branches: `cursor/…` of `feat/…`, PR naar **`staging`**.
- Na test op staging: PR `staging` → `master`.
- Branch-protectie: alleen via PR, geen force-push.
- CI faalt als een PR naar `master` niet vanaf `staging` komt.

## Releases

- Semver-tags op `master`: `v0.1.0`, …
- `CHANGELOG.md` (Keep a Changelog).
- Push naar `staging` → automatische staging-deploy (slaagt over als secrets ontbreken).
- Productie-deploy alleen bij tag `v*.*.*` op `master` (+ handmatige `workflow_dispatch` voor herdeploy van een tag).

## Secrets

Nooit hostnamen van servers, cPanel-gebruikers, wachtwoorden of private paden in code/docs/workflows committen. Alles via GitHub Environments/Secrets (`SITE_NAME`, `BASE_URL`, `DB_*`, `SMTP_*`, `DEPLOY_*`, `CRON_TOKEN`, …).

## Lokaal

Zie `docs/INSTALL.md`. PHP 8.2, SQLite voor tests.
