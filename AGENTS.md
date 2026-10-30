# Agents / deploy-notities

Korte handleiding voor Cloud Agents en hosting. Geen secrets of tokens in deze file.

## Branchflow

| Branch | Rol |
|--------|-----|
| `staging` | Bron voor staging. Feature-PR’s landen hier. |
| `master` | Productiebron. Alleen via PR vanaf `staging`. |
| `deploy-staging` | Oud build-artefact (UAPI). Wordt niet meer door CI gebruikt; niet mergen. |

## Staging op cPanel (Hoasted, **noshell** — geen SSH)

Deploy gaat **niet** via cPanel `:2083` (Imunify360 WebShield challenge). Wel via HTTPS
naar `https://staging.gratisplanten.nl/deploy-hook.php` (poort 443).

### Serverlayout (vast; geen secrets)

| Pad | Functie |
|-----|---------|
| `/home/gratisplanten/.deploy/` | Config buiten docroot (`staging.php` / later `production.php`), mode `0700` |
| `/home/gratisplanten/app-staging` | Laravel-app buiten de webroot (`releases/<sha>`, `current`, shared `storage/`, `.env`) |
| `/home/gratisplanten/staging.gratisplanten.nl` | Docroot (`public/`-inhoud + `index.php` → app + `deploy-hook.php`) |
| `/home/gratisplanten/public_html` | Later prod-docroot (+ parked `gratisstekjes.nl`) |
| `/home/gratisplanten/app-prod` | Later prod-app |
| `/home/gratisplanten/repo-staging` | Legacy cPanel Git-clone (niet meer nodig voor hook-deploy) |

Shell/SSH blijft uit. **`POST /deploy/run`** blijft de betrouwbare migrate/cache-route nadat bestanden staan.

### Eenmalige hook-installatie

Zie **`deploy/hook/INSTALL.md`**. Kort:

1. `./scripts/make-deploy-hook-config.sh staging` (zet `DEPLOY_HOOK_TOKEN`, print config met hash).
2. Config → `/home/gratisplanten/.deploy/staging.php` (0600).
3. Upload `deploy/hook/deploy-hook.php` + `lib.php` naar de staging-docroot.

### GitHub secrets — exacte namen

**Environment `staging`**

| Secret | Rol |
|--------|-----|
| `DEPLOY_HOOK_TOKEN` | Bearer voor `/deploy-hook.php` (plaintext; op server alleen hash) |
| `DEPLOY_TOKEN` | Bearer voor `POST /deploy/run` |
| `STAGING_BASE_URL` | Staging-URL (HTTPS) |
| `STAGING_DB_HOST` | MySQL-host |
| `STAGING_DB_NAME` | Database |
| `STAGING_DB_USER` | DB-user |
| `STAGING_DB_PASS` | DB-wachtwoord |

**Environment `production`** (workflow `deploy-prod-hook.yml`, tags `v*.*.*`)

| Secret | Rol |
|--------|-----|
| `DEPLOY_HOOK_TOKEN` | Bearer voor `/deploy-hook.php` op `public_html` |
| `DEPLOY_TOKEN` | Bearer voor `POST /deploy/run` |
| `PROD_BASE_URL` | Productie-URL (HTTPS) |
| `PROD_DB_HOST` | MySQL-host |
| `PROD_DB_NAME` | Database |
| `PROD_DB_USER` | DB-user |
| `PROD_DB_PASS` | DB-wachtwoord |

**Repository of environment (gedeeld)**

`SITE_NAME`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `CRON_TOKEN`, `ADMIN_PASSWORD`.

Optioneel: `APP_KEY` — anders bestaande server-`.env` behouden, anders eenmalig genereren in de hook.

`CPANEL_HOST` / `CPANEL_USER` / `CPANEL_API_TOKEN` zijn voor de HTTPS-hook-flow **niet** nodig (legacy UAPI-scripts blijven in `scripts/` voor handmatig allowlisted IP).

### Workflows

- **`deploy-staging-cpanel.yml`** — actief: push `staging` of `workflow_dispatch`. Build → zip → chunked upload → `/deploy/run` → smoke. Ontbrekende hele secret-set → skip (notice). Gedeeltelijk → fail. Hook 404 → fail met INSTALL-stappen.
- **`deploy-prod-hook.yml`** — tags `v*.*.*` + `workflow_dispatch`; environment `production`. **Niet** bij merges naar staging.
- **`scripts/deploy-hook-upload.sh`** — client (chunk 5 MiB, sha256).
- **`scripts/make-deploy-hook-config.sh`** — token + `gh secret set` + print config-hash.
- **`deploy-staging.yml`** — **retired** (oude SSH). Alleen `workflow_dispatch` + `ALLOW_LEGACY_SSH_STAGING=1`.
- Legacy: `scripts/cpanel-deploy.sh` (UAPI `:2083`) alleen handmatig vanaf allowlisted IP.

### Deploy-volgorde (staging)

1. CI build (`composer --no-dev`, Vite)
2. Zip-artefact (geen `.env` / `node_modules` / tests)
3. Chunked POST naar `/deploy-hook.php` + `.env` (0600); storage behouden
4. **`POST /deploy/run`** → migrate + DeelplekkenSeeder + caches (**moet 200**)
5. Smoke: 200, footer-versie (`Versie x.y.z`), staging noindex

### Prod (later)

Zelfde hook-patroon: `app-prod` + `public_html`. Parked `gratisstekjes.nl` → 301 naar `gratisplanten.nl`. `.env` zet `DEPLOY_ENDPOINT_ENABLED=true` zodat `/deploy/run` werkt.
