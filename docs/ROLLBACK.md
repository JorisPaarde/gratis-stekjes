# Rollback-plan

## Code

1. Zoek de vorige werkende semver-tag op `master` (bijv. `v0.1.0`).
2. Herdeploy die tag via de production-workflow (`workflow_dispatch` met tag-input, of opnieuw de tag pushen is niet nodig — kies de tag in de workflow).
3. Controleer of de site noindex-headers nog kloppen en of `/` laadt.

## Database

1. **Voorkeur:** draai de bijbehorende down-migratie(s) met `php scripts/migrate.php down [n]` als die veilig en getest zijn.
2. **Anders:** zet de mysqldump-back-up terug die de production-deploy in de private map (buiten de webroot) heeft gezet vóór de migratie.
3. Back-ups en config staan buiten de webroot; paden komen uit secrets/`PRIVATE_PATH`, niet uit deze repo.

## Staging

Staging mag je opnieuw deployen vanaf de `staging`-branch. Ontbreken staging-secrets, dan slaagt de workflow met een skip (geen falen).
