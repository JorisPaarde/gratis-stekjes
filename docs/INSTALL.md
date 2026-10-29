# Installatie (lokaal en server)

Precieze stappen. Geen secrets, hostnamen of private paden in deze repo — die komen uit environment / GitHub Secrets.

## Lokaal (PHP 8.2 + SQLite)

1. Kopieer `config.example.php` naar een pad **buiten** de webroot, of naar `config.php` in de projectroot (staat in `.gitignore`).
2. Zet in die config minimaal:
   - `db_driver` => `sqlite`
   - `db_path` => pad naar een schrijfbaar `.sqlite`-bestand buiten de webroot
   - `site_name`, `site_url` (bijv. `http://127.0.0.1:8080`)
   - `session_secure` => `false` zonder HTTPS
   - SMTP mag leeg; tests kunnen `STEKJES_MAIL_CAPTURE` gebruiken
3. Bouw statische pagina’s:
   ```sh
   python3 scripts/build_site.py
   python3 scripts/build_manifest.py
   ```
4. Migraties:
   ```sh
   export STEKJES_TEST_DB=/tmp/stekjes-dev.sqlite
   # of wijs config naar sqlite
   php scripts/migrate.php up
   php scripts/migrate.php status
   ```
5. Start:
   ```sh
   php -S 127.0.0.1:8080 -t .
   ```

## Server (MySQL)

1. Deploy-workflow schrijft `config.php` **buiten** de document root (pad via secrets, bijv. sibling-map `…/stekjes-private/config.php`). Nooit in git.
2. Secrets bevatten o.a. `SITE_NAME`, `BASE_URL`, `DB_*`, `SMTP_*`, `CRON_TOKEN`, `ADMIN_PASSWORD`, `DEPLOY_*`.
3. Na code-sync: `php scripts/migrate.php up` (vanaf de docroot of met `STEKJES_CONFIG` gezet).
4. Productie-deploy maakt eerst een `mysqldump` in de private map; zie `docs/ROLLBACK.md`.

## Branches en releases

Zie `CONTRIBUTING.md` en `CHANGELOG.md`. Kern-PR’s → `staging`. Productie alleen via tag `v*.*.*` op `master`.
