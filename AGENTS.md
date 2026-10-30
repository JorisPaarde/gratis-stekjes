# Agents / deploy-notities

Korte handleiding voor Cloud Agents en hosting. Geen secrets of tokens in deze file.

## Branchflow

| Branch | Rol |
|--------|-----|
| `staging` | Bron voor staging. Feature-PR’s landen hier. |
| `master` | Productiebron. Alleen via PR vanaf `staging`. |
| `deploy-staging` | **Build-artefact** (vendor + `public/build` + gegenereerde `.cpanel.yml`). Force-push OK. Niet mergen naar staging/master. |

## Staging op cPanel (Hoasted, **noshell** — geen SSH)

### Serverlayout (vast; geen secrets)

| Pad | Functie |
|-----|---------|
| `/home/gratisplanten/repo-staging` | cPanel Git-clone; branch **`deploy-staging`** (workflow: `VersionControl::update`) |
| `/home/gratisplanten/repo-prod` | cPanel Git-clone op **`master`** (later prod) |
| `/home/gratisplanten/app-staging` | Laravel-app buiten de webroot |
| `/home/gratisplanten/staging.gratisplanten.nl` | Docroot (`public/`-inhoud + `index.php` → `../app-staging`) |
| `/home/gratisplanten/public_html` | Later prod-docroot (+ parked `gratisstekjes.nl`) |
| `/home/gratisplanten/app-prod` | Later prod-app |

Deploy key: read-only op deze GitHub-repo. Shell/SSH blijft uit → **`.cpanel.yml`-artisan is onbetrouwbaar**; **`POST /deploy/run` is de betrouwbare migrate/cache-route**.

### Belangrijk: `.cpanel.yml` alleen op `deploy-staging`

Nooit `.cpanel.yml` op `staging`/`master` committen (`repo-prod` volgt master). CI genereert hem alleen in het build-artefact. Voorbeeld: `deploy/cpanel.yml.example`.

### Wat hosting in cPanel zet

1. Git Version Control: `repo-staging` + deploy key; branch `deploy-staging`.
2. Docroot subdomain → map `staging.gratisplanten.nl` (niet `…/public`).
3. MySQL staging-DB + user.
4. API-token met VersionControl, VersionControlDeployment, Fileman.
5. Geen cron vanuit deze deploy.

### GitHub secrets — exacte namen

**Environment `staging`**

| Secret | Rol |
|--------|-----|
| `CPANEL_HOST` | `https://<host>:2083` |
| `CPANEL_USER` | cPanel-user |
| `CPANEL_API_TOKEN` | API-token |
| `DEPLOY_TOKEN` | Bearer voor `POST /deploy/run` |
| `STAGING_BASE_URL` | Staging-URL (HTTPS) |
| `STAGING_DB_HOST` | MySQL-host |
| `STAGING_DB_NAME` | Database |
| `STAGING_DB_USER` | DB-user |
| `STAGING_DB_PASS` | DB-wachtwoord |

**Repository (gedeeld)**

`SITE_NAME`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `CRON_TOKEN`, `ADMIN_PASSWORD`.

Geen `APP_KEY`-secret: bestaande `.env` op de server bewaren, anders eenmalig genereren via de deploy.

Paden (`repo-staging` / `app-staging` / docroot) staan vast in workflow + `scripts/cpanel-deploy.sh` (geen aparte secrets).

### Workflows

- **`deploy-staging-cpanel.yml`** — actief: push `staging` of `workflow_dispatch`. Ontbrekende `CPANEL_HOST`+`USER`+`TOKEN` → skip (notice). Gedeeltelijk → fail.
- **`scripts/cpanel-deploy.sh`** — zelfde stappen lokaal/vaste machine (Imunify op `:2083`; geen GitHub-IP-whitelist).
- **`deploy-staging.yml`** — **retired** (oude SSH). Alleen `workflow_dispatch` + secret `ALLOW_LEGACY_SSH_STAGING=1`; anders hard fail. Environment `staging` deelt nu gratisplanten-DB/URL — niet per ongeluk tegen de oude host draaien.

### Deploy-volgorde

1. CI build → force-push `deploy-staging` (+ `.cpanel.yml`)
2. UAPI update + deployment (bestandskopie; artisan in tasks mag falen)
3. `.env` via Fileman (APP_KEY behouden indien aanwezig)
4. **`POST /deploy/run`** → migrate + DeelplekkenSeeder + caches (**moet 200**)
5. Smoke: 200, noindex, footer-versie

### Prod (later)

Zelfde patroon: `app-prod` + `public_html`, eigen build-branch/`.cpanel.yml`. Parked `gratisstekjes.nl` → 301 naar `gratisplanten.nl`.
