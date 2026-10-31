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
- Push naar `staging` → `deploy-staging-cpanel.yml` (HTTPS-hook; skip met notice als hook-secrets ontbreken).
- Productie-hook: `deploy-prod-hook.yml` bij tag `v*.*.*` (+ `workflow_dispatch`). Niet bij merges naar staging.
- Branch `deploy-staging` is legacy artefact; niet mergen. Geen `.cpanel.yml` op `staging`/`master`.

## Secrets

Nooit tokens/wachtwoorden in git of PR-tekst. Exacte namen + hook-install: `AGENTS.md` / `deploy/hook/INSTALL.md` (`DEPLOY_HOOK_TOKEN`, `DEPLOY_TOKEN`, `STAGING_*` / `PROD_*`, `SITE_NAME`, `SMTP_*`, `CRON_TOKEN`, `ADMIN_PASSWORD`, optioneel `BACKUP_ENCRYPTION_KEY`).

## Lokaal

Zie `docs/INSTALL.md`. PHP 8.2, SQLite voor tests.
