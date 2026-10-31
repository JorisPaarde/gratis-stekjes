# Versleutelde backups (DB + uploads)

Doel: regelmatige database- én `storage/app/public`-backups voor **staging** en **productie**, versleuteld at rest. De sleutel leeft alleen in GitHub secrets / server-`.env` — nooit in git of logs.

## Wat er al in de repo zit

| Onderdeel | Pad / mechanisme |
| --- | --- |
| Artisan backup | `php artisan backup:database-encrypted` |
| Artisan decrypt (restore-hulp) | `php artisan backup:decrypt <in.enc> <out>` |
| HTTP-trigger (noshell) | `GET /cron/backup` met header `X-Cron-Token` |
| Download ciphertext | `GET /cron/backup?download=db\|storage` of `?download=<basename>` |
| Lijst | `GET /cron/backup?list=1` |
| Off-site kopie | Workflow `.github/workflows/backup.yml` (dagelijks 03:15 UTC) → GitHub artifact, retentie 30 dagen |
| Server-retentie | `BACKUP_RETAIN_DAYS` (default 30) wist oudere `storage/backups/*.enc` |
| Deploy schrijft sleutel | `BACKUP_ENCRYPTION_KEY` uit secret → `.env`; leeg → hook behoudt bestaande waarde |

## Formaat

- Algoritme: **AES-256-CBC**, sleutel = `SHA-256(BACKUP_ENCRYPTION_KEY)`.
- Blob: magic `GPB1` + 16-byte IV + ciphertext.
- DB-bestand: `storage/backups/db-YYYYMMDDTHHMMSSZ.sql.gz.enc` (rechten `0600`, map `0700`).
- Storage: `storage/backups/storage-YYYYMMDDTHHMMSSZ.tar.gz.enc` (inhoud van `storage/app/public`).
- MySQL: bij voorkeur `mysqldump`; anders PDO-fallback (tabellen + data, geen routines).

## Artisan (lokaal / server-CLI)

```bash
php artisan backup:database-encrypted
# optioneel:
#   --path=/pad/db.sql.gz.enc
#   --storage-path=/pad/storage.tar.gz.enc
#   --no-storage
#   --retain-days=30
```

Sleutel: `BACKUP_ENCRYPTION_KEY` uit `.env` (valt terug op `APP_KEY` — liever apart).

## Restore (getest in CI via roundtrip)

### 1. Ciphertext ophalen

- **Off-site:** GitHub → Actions → workflow *Encrypted backups* → artifact `encrypted-backups-<run_id>` (map `staging/` / `production/`).
- **Op de server:** `storage/backups/` (app-map buiten docroot) of `GET /cron/backup?download=db` met `X-Cron-Token`.

### 2. Ontsleutelen

Zet `BACKUP_ENCRYPTION_KEY` in je lokale `.env` (zelfde waarde als GitHub secret) of geef `--key=` alleen lokaal/tests:

```bash
php artisan backup:decrypt staging/db-….sql.gz.enc /tmp/restore.sql.gz
php artisan backup:decrypt staging/storage-….tar.gz.enc /tmp/restore-storage.tar.gz
```

Of met PHP one-liner (zelfde algoritme):

```bash
php -r '
$key = getenv("BACKUP_ENCRYPTION_KEY");
$blob = file_get_contents($argv[1]);
$iv = substr($blob, 4, 16);
$ct = substr($blob, 20);
$pt = openssl_decrypt($ct, "aes-256-cbc", hash("sha256", $key, true), OPENSSL_RAW_DATA, $iv);
file_put_contents($argv[2], $pt);
' backup.sql.gz.enc backup.sql.gz
```

### 3. Database terugzetten

```bash
gunzip -c /tmp/restore.sql.gz > /tmp/restore.sql
# MySQL (pas host/db/user aan; wachtwoord via MYSQL_PWD, niet op de command line loggen):
mysql -h HOST -u USER DATABASE < /tmp/restore.sql
```

SQLite-backups (tests/dev) zijn een gzip van het `.sqlite`-bestand: `gunzip -c` → bestand terugplaatsen.

### 4. Uploads terugzetten

```bash
mkdir -p storage/app/public
tar -xzf /tmp/restore-storage.tar.gz -C storage/app/public
```

### 5. Na restore

- `php artisan config:cache` / `route:cache` / `view:cache` indien van toepassing.
- Spot-check login + aanbod; **geen** mailblast naar gebruikers.

## Planning

Geen cPanel-SSH nodig. GitHub Actions roept `/cron/backup` aan (zelfde patroon als `/cron`). Handmatig: *Actions* → *Encrypted backups* → *Run workflow*.

## Secrets / config

| Naam | Waar | Rol |
| --- | --- | --- |
| `BACKUP_ENCRYPTION_KEY` | GitHub secret (repo of env `staging` / `production`) + server-`.env` | AES-sleutelbron |
| `BACKUP_RETAIN_DAYS` | Optionele GitHub variable / `.env` | Rolling retentie op server (default 30) |
| `CRON_TOKEN` | Bestaand | Auth voor `/cron/backup` |
| Artifact-retentie | `backup.yml` → `retention-days: 30` | Off-site rolling |

Genereer een sleutel (lokaal, niet committen):

```bash
openssl rand -base64 32
# daarna: gh secret set BACKUP_ENCRYPTION_KEY --body '…'
# en opnieuw deployen zodat .env de sleutel krijgt
```

## Open / handmatig (Joris)

| Punt | Status |
| --- | --- |
| `BACKUP_ENCRYPTION_KEY` als GitHub secret zetten (staging + production of repo-level) | **Wacht op Joris** |
| Eenmalig her-deployen na secret-set zodat server-`.env` de sleutel krijgt | **Wacht op Joris** |
| Optioneel: aparte sleutels per omgeving | Keuze Joris |
| Artifact-download lokaal bewaren buiten GitHub (extra off-site) | Optioneel |

Geen secrets of sleutelwaarden in git of CI-logs.
