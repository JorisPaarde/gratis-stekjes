# Deploy-hook installatie (eenmalig, cPanel File Manager)

Geen SSH/shell nodig. De GitHub Actions-runner praat alleen HTTPS (:443) met
`https://staging.gratisplanten.nl/deploy-hook.php` — niet met cPanel `:2083`.

**Nooit** het ruwe token in chat, tickets of git zetten. Gebruik het helper-script
vanaf een vertrouwde terminal (met `gh` ingelogd).

## Staging — genummerde stappen

### A. Token + GitHub secret + config-inhoud (lokaal)

1. Clone/checkout deze repo op een machine met GitHub CLI (`gh auth status` OK).
2. Draai:
   ```sh
   ./scripts/make-deploy-hook-config.sh staging
   ```
   Dit script:
   - genereert een cryptografisch random token;
   - zet hem als environment-secret: `gh secret set DEPLOY_HOOK_TOKEN --env staging`;
   - print **alleen** de PHP-config (met `token_hash`, zonder plaintext token).
3. Kopieer de geprinte config naar het klembord. Het token zelf wordt niet getoond.

### B. Config buiten de docroot (cPanel File Manager)

4. Log in op cPanel → **File Manager**.
5. Ga naar `/home/gratisplanten/`.
6. Maak map `.deploy` (als die nog niet bestaat).
7. Rechtsklik `.deploy` → **Permissions** → `700` (alleen owner).
8. In `.deploy`: **+ File** → `staging.php`.
9. Bewerk `staging.php`, plak de config uit stap 3, **Save**.
10. Permissions op `staging.php` → `600`.

Voorbeeldstructuur van die config (waarden komen uit het script):

```php
<?php
return [
    'token_hash' => '...',  // password_hash of sha256-hex, NOOIT plaintext
    'app_dir'    => '/home/gratisplanten/app-staging',
    'docroot'    => '/home/gratisplanten/staging.gratisplanten.nl',
    'env'        => 'staging',
];
```

### C. Hook in de docroot

11. Ga naar `/home/gratisplanten/staging.gratisplanten.nl/` (staging-docroot).
12. Upload uit deze repo:
    - `deploy/hook/deploy-hook.php` → `deploy-hook.php`
    - `deploy/hook/lib.php` → `lib.php`
13. Permissions beide bestanden: `644` (of `600` als PHP ze nog kan lezen).
14. Controleer in de browser/curl (verwacht JSON, geen HTML-challenge):
    ```sh
    curl -sS -o /dev/null -w '%{http_code}\n' -X POST \
      https://staging.gratisplanten.nl/deploy-hook.php
    ```
    Zonder token: `403` of `405` met `{"ok":false,...}` is goed.
    `404` = bestand staat verkeerd; HTML “One moment, please…” = verkeerde host/poort.

### D. Overige GitHub secrets (environment `staging`)

Zorg dat naast `DEPLOY_HOOK_TOKEN` ook staan (exacte namen):

| Secret | Rol |
|--------|-----|
| `DEPLOY_TOKEN` | Bearer voor `POST /deploy/run` (migrate/cache) |
| `STAGING_BASE_URL` | `https://staging.gratisplanten.nl` |
| `STAGING_DB_HOST` | MySQL-host |
| `STAGING_DB_NAME` | Database |
| `STAGING_DB_USER` | DB-user |
| `STAGING_DB_PASS` | DB-wachtwoord |
| `SITE_NAME` | Site-/mailnaam |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_SECURE` / `SMTP_USER` / `SMTP_PASS` / `SMTP_FROM` | Mail |
| `CRON_TOKEN` | Cron-endpoint |
| `ADMIN_PASSWORD` | Beheer |

Optioneel: `APP_KEY` — **alleen voor de allereerste install** (geen `.env` op de
server). Heeft de server al een `APP_KEY`, dan behoudt de hook die altijd — ook als
het GitHub-secret anders of leeg is. Nooit handmatig draaien/`key:generate` op prod
zonder backup: encrypted casts (feedback, berichten, telefoon, …) worden onleesbaar.
Vóór elke `.env`-herschrijf maakt de hook `app-*/.env.backup` (mode 0600).

Optioneel (backups): `BACKUP_ENCRYPTION_KEY` — zie `docs/backups.md`. Lege upload → bestaande server-waarde behouden.

`CPANEL_HOST` / `CPANEL_USER` / `CPANEL_API_TOKEN` zijn voor deze flow **niet** meer nodig.

### E. Eerste deploy

15. Push naar `staging` of `workflow_dispatch` op **Deploy staging (cPanel)**.
16. Bij ontbrekende hook (HTTP 404) faalt de workflow met `::error::` en herhaalt deze stappen.

## Productie (later, zelfde patroon)

1. `./scripts/make-deploy-hook-config.sh production`  
   → secret `DEPLOY_HOOK_TOKEN` in environment **`production`**, config voor `public_html`.
2. Config: `/home/gratisplanten/.deploy/production.php` (`app_dir` = `…/app-prod`, `docroot` = `…/public_html`, `env` = `production`).
3. Upload `deploy-hook.php` + `lib.php` naar `/home/gratisplanten/public_html/`.
4. Secrets: zie `AGENTS.md` (PROD_*). Workflow: `deploy-prod-hook.yml` (tags `v*.*.*`). **Niet** bij merges naar staging.

## LiteSpeed / lsphp na deploy

Na release-swap herschrijft de hook `/home/<account>/.lsphp_restart.txt` (**inhoud**,
niet alleen mtime) in de account-home — hosting bevestigde dat handmatig herschrijven
lsphp wél herstart, terwijl `touch()` vaak niet aansloeg. Daarnaast: entrypoint-mtimes
bumpen, `POST` action `lsphp_restart` / `public/deploy-lsphp-restart.php`, en migrate
via CLI (`action=migrate`) in de nieuwe release-map. De upload-workflow wacht op
footer-versie + deelplekken-count met retries.

## Beveiliging

- Alleen `POST` over HTTPS; Bearer-token (hash in config).
- File-based rate limit bij auth-fails.
- Antwoorden: klein JSON, geen secrets/stack traces.
- `.env` op de server: mode `0600`.
