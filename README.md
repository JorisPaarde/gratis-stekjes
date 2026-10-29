# Gratis stekjes

Een plek waar mensen gratis stekjes aan elkaar uitdelen. Je zet in je profiel wat je over hebt, iemand uit de buurt vindt het, komt het ophalen, en het enige wat het kost is een praatje bij de voordeur.

## Neem het mee

Dit project is van iedereen. Deel het, gebruik het, kopieer het, pas het aan, begin er je eigen versie mee in je dorp, wijk of land. Je hoeft niets te vragen en niemand te noemen. Doe ermee wat je wilt.

Het doel is simpel: de wereld een stukje groener maken. Meer planten in meer tuinen, op meer balkons en in meer vensterbanken, en een beetje meer contact tussen mensen die bij elkaar in de buurt wonen. Hoe vaker dit idee ergens opduikt, hoe beter.

En heb je zelf iets over, een stekje, zaden, een pot of wat kennis: deel het uit.

## Het idee

- **Gratis.** Niets wordt verkocht. Geven en ophalen, meer niet.
- **Van mens tot mens.** Je zoekt op postcode of plaats wat er in de buurt is en stuurt via de site een berichtje. Pas als jullie allebei verder willen, zien jullie elkaars contactgegevens.
- **Privacy eerst.** Op de kaart staat alleen een benaderde plek (postcode-4 of wijk), nooit een adres of e-mailadres.
- **Groen en eerlijk.** Planten die invasief zijn in Nederland horen er niet tussen; de site waarschuwt daarvoor, met bronnen.

## Zelf draaien

De site is bewust licht gehouden: statische pagina's met een klein stukje PHP en een MySQL- of SQLite-database. Dat draait op vrijwel elke gewone webhosting.

Wat je nodig hebt:

- PHP 8.2 of nieuwer
- MySQL/MariaDB (of SQLite om lokaal te testen)
- Een mailbox met SMTP voor de bevestigingsmails

Precieze stappen (kort):

1. Clone de repo en gebruik **PHP 8.2+**.
2. Kopieer `config.example.php` naar een `config.php` **buiten** de webroot (of zet `STEKJES_CONFIG` naar dat pad). Vul `site_name`, `site_url` / `BASE_URL`, database en SMTP in. Nooit committen.
3. Bouw de statische pagina's: `python3 scripts/build_site.py && python3 scripts/build_manifest.py`
4. Migraties: `php scripts/migrate.php up` (status: `php scripts/migrate.php status`)
5. Lokaal: SQLite via `db_driver=sqlite` + `db_path`, daarna `php -S 127.0.0.1:8080 -t .`
6. Productie/staging: MySQL; deploy-workflows schrijven config buiten de webroot uit secrets en draaien migraties.

Uitgebreider: [`docs/INSTALL.md`](docs/INSTALL.md), branchflow: [`CONTRIBUTING.md`](CONTRIBUTING.md), rollback: [`docs/ROLLBACK.md`](docs/ROLLBACK.md).

De naam en het webadres staan alleen in de config — een ander domein is config + DNS, geen codewijziging.

## Licentie

- **Code:** [The Unlicense](LICENSE). Publiek domein, zonder voorwaarden.
- **Teksten, tips en andere inhoud:** [CC0 1.0](LICENSE-CONTENT.md). Ook publiek domein.

Bronnen die we gebruiken (zoals de EU-lijst van invasieve soorten) houden hun eigen voorwaarden; die staan erbij vermeld.
