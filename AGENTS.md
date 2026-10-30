# Agents / deploy-notities

Korte handleiding voor Cloud Agents en hosting. Geen secrets of tokens in deze file.

## Branchflow

| Branch | Rol |
|--------|-----|
| `staging` | Bron voor staging. Feature-PR’s landen hier. |
| `master` | Productiebron. Alleen via PR vanaf `staging`. |
| `deploy-staging` | **Build-artefact** (vendor + `public/build` + gegenereerde `.cpanel.yml`). Force-push OK. Niet mergen naar staging/master. |

## Staging op cPanel (Hoasted, geen SSH-shell)

### Serverlayout (hosting)

| Pad | Functie |
|-----|---------|
| `/home/gratisplanten/repo-staging` | cPanel Git-clone; moet branch **`deploy-staging`** volgen (workflow zet dit via `VersionControl::update`) |
| `/home/gratisplanten/repo-prod` | cPanel Git-clone op **`master`** (later prod; nu niet aanraken) |
| `/home/gratisplanten/app-staging` | Laravel-app buiten de webroot |
| `/home/gratisplanten/staging.gratisplanten.nl` | Docroot (inhoud van `public/` + `index.php` → `../app-staging`) |
| `/home/gratisplanten/public_html` | Later prod-docroot (+ parked `gratisstekjes.nl`) |
| `/home/gratisplanten/app-prod` | Later prod-app (zelfde patroon) |

Deploy key: read-only op deze GitHub-repo (cPanel trekt; Actions pusht alleen naar GitHub).

### Belangrijk: `.cpanel.yml` alleen op `deploy-staging`

De staging-`.cpanel.yml` wordt **alleen** in het build-artefact op `deploy-staging` gezet (paden uit secrets). Nooit in de root van `staging` of `master` committen — anders kan `repo-prod` (master) per ongeluk een staging-deploy draaien. Voorbeeld: `deploy/cpanel.yml.example`.

### Wat hosting in cPanel zet

1. **Git Version Control**: clone `repo-staging` met deploy key; branch na eerste succesvolle Actions-run: `deploy-staging`.
2. **Docroot** subdomain `staging.gratisplanten.nl` → map `staging.gratisplanten.nl` (niet `…/public`).
3. **MySQL**-database + user voor staging.
4. **API-token** (cPanel → Manage API Tokens) met rechten voor o.a. `VersionControl`, `VersionControlDeployment`, `Fileman` (get/save file content).
5. PHP 8.2 (ea-php82) indien CLI beschikbaar; migraties gaan sowieso via `POST /deploy/run`.
6. Geen cron aanzetten vanuit deze deploy (aparte workflow).

### GitHub Environment `staging` — secretnamen

**cPanel / paden**

| Secret | Voorbeeld / betekenis |
|--------|------------------------|
| `CPANEL_HOST` | `https://<host>:2083` (incl. scheme + poort) |
| `CPANEL_USER` | cPanel-user |
| `CPANEL_API_TOKEN` | API-token |
| `CPANEL_REPO_ROOT` | `/home/gratisplanten/repo-staging` |
| `STAGING_APP_DIR` | `/home/gratisplanten/app-staging` |
| `STAGING_DEPLOY_PATH` | `/home/gratisplanten/staging.gratisplanten.nl` |
| `STAGING_BASE_URL` | `https://staging.gratisplanten.nl` |
| `DEPLOY_TOKEN` | Bearer voor `POST /deploy/run` (lang, random) |

**App** (zelfde set als de oude SSH-deploy)

`SITE_NAME`, `STAGING_DB_HOST`, `STAGING_DB_NAME`, `STAGING_DB_USER`, `STAGING_DB_PASS`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `CRON_TOKEN`, `ADMIN_PASSWORD`, optioneel `APP_KEY` (anders: bestaande op server bewaren, anders genereren).

**Schakelaar oude SSH-deploy:** `STAGING_USE_CPANEL=1` → `deploy-staging.yml` (SSH) skipt met notice.

### Workflows

- `deploy-staging-cpanel.yml` — push naar `staging` of `workflow_dispatch`. Mist `CPANEL_HOST`+`USER`+`TOKEN` volledig → skip (notice, groen). Gedeeltelijk → fail.
- `scripts/cpanel-deploy.sh` — zelfde UAPI-stappen lokaal/vanaf vaste machine (Imunify blokkeert vaak GitHub-runner-IP’s op `:2083`; geen whitelist van GitHub-ranges). Retries + duidelijke fout bij 403/non-JSON.
- Oude `deploy-staging.yml` (SSH) blijft tot die bewust uitgezet wordt.

### Deploy-volgorde

1. CI: `composer install --no-dev` + `npm ci && npm run build`
2. Force-push artefact → `deploy-staging` (met `.cpanel.yml`)
3. UAPI `VersionControl::update` (branch `deploy-staging`) + `VersionControlDeployment::create`, poll tot klaar
4. `.env` via `Fileman::save_file_content` (APP_KEY behouden indien aanwezig)
5. `POST /deploy/run` (Bearer `DEPLOY_TOKEN`) → migrate + DeelplekkenSeeder + caches; moet HTTP 200
6. Smoke: homepage 200, noindex, footer-versie

### Prod (later, zelfde patroon)

`app-prod` + `public_html`; eigen build-branch / `.cpanel.yml` alleen daar. Parked `gratisstekjes.nl` → 301 naar `https://gratisplanten.nl` (middleware + `.htaccess`; uit op staging/local).
