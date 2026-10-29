# Versleutelde database-backups

## Artisan-command (in de repo)

```bash
php artisan backup:database-encrypted
# optioneel: --path=/pad/naar/bestand.sql.gz.enc
```

- Maakt een gzip-dump (MySQL via `mysqldump`, SQLite via bestandslezing) en versleutelt die met **AES-256-CBC**.
- Sleutel: `BACKUP_ENCRYPTION_KEY` uit `.env` (nooit in git). Valt terug op `APP_KEY` als die leeg is — liever een aparte backup-sleutel.
- Output: `storage/backups/db-YYYYMMDDTHHMMSSZ.sql.gz.enc` (rechten `0600`, map `0700`).
- Formaat: magic `GPB1` + 16-byte IV + ciphertext.

## Ontsleutelen (lokaal, met de sleutel)

```bash
# Voorbeeld met PHP (zelfde algoritme als het command):
php -r '
$key = getenv("BACKUP_ENCRYPTION_KEY");
$blob = file_get_contents($argv[1]);
$iv = substr($blob, 4, 16);
$ct = substr($blob, 20);
$pt = openssl_decrypt($ct, "aes-256-cbc", hash("sha256", $key, true), OPENSSL_RAW_DATA, $iv);
file_put_contents($argv[2], $pt);
' backup.sql.gz.enc backup.sql.gz
gunzip -c backup.sql.gz > backup.sql
```

## Open punten (server / Joris)

| Punt | Status |
| --- | --- |
| Cron die het command periodiek draait | **Open** — op cPanel inplannen (`php artisan backup:database-encrypted`) |
| Off-site opslag (niet alleen webserver-disk) | **Open** — bv. downloaden naar eigen schijf / object storage buiten de docroot |
| `BACKUP_ENCRYPTION_KEY` zetten in staging/productie-`.env` | **Open** — aparte sleutel, veilig bewaren |
| Bewaartermijn van backupbestanden | **Open** — voorstel: 30 dagen rolling, oudere `.enc` wissen |
| `storage/backups/` buiten webroot houden | App-map staat al buiten docroot op deze hosting; niet publiceren |

Geen secrets of sleutelwaarden in git.
