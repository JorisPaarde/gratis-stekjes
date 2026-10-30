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
- Push naar `staging` → `deploy-staging-cpanel.yml` (skip met notice als cPanel-secrets ontbreken).
- Branch `deploy-staging` is alleen build-artefact (force-push); niet mergen. Geen `.cpanel.yml` op `staging`/`master`.
- Productie-deploy alleen bij tag `v*.*.*` op `master` (+ handmatige `workflow_dispatch` voor herdeploy van een tag).

## Secrets

Nooit tokens/wachtwoorden in git of PR-tekst. Exacte namen + cPanel-setup: `AGENTS.md` (`CPANEL_*`, `DEPLOY_TOKEN`, `STAGING_BASE_URL`, `STAGING_DB_*`, `SITE_NAME`, `SMTP_*`, `CRON_TOKEN`, `ADMIN_PASSWORD`).

## Lokaal

Zie `docs/INSTALL.md`. PHP 8.2, SQLite voor tests.
