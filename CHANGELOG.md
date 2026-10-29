# Changelog

Alle noemenswaardige wijzigingen in dit project. Formaat: [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/),
versies volgens [Semantic Versioning](https://semver.org/lang/nl/).

## [Unreleased]

### Added

- AVG (PR C): privacyverklaring aangescherpt, JSON-gegevensexport, account zelf verwijderen, helper-verwijderen via e-mail, retentie/purge (rate-limits, geocode-cache) via cron.
- Aanbod verloopt na 60 dagen: 7 dagen van tevoren één herinneringsmail (SMTP) met eenmalige, ondertekende verlenglink; verlengen telt opnieuw 60 dagen. Verlopen items verdwijnen uit zoeken/kaart, blijven zichtbaar voor de eigenaar. Cron-job via `cron.php` + bestaande GitHub Actions-schedule.
- Kern-webapp (PR A): accounts (registratie, double opt-in, login, wachtwoord-reset), profiel met benaderde locatie (PDOK), aanbod (vrije tekst + type), zoeken met kaart/lijst (Leaflet/OSM + haversine), berichten met wederzijds contact-akkoord, melden/blokkeren.
- Helpers-aanmelding zonder volledig account; teller “X mensen doen al mee” met drempel.
- Simpele admin (helpers, meldingen, partnermarkers-overzicht).
- Anti-spam: honeypot, tijdsdrempel, rate-limit, CSRF, wegwerpmail-lijst (MIT).
- PWA: manifest, iconen, service worker (shell-cache, geen API/berichten).
- MySQL op server, SQLite in CI; versioned migraties; deploy/cron-workflows via secrets.
- Documentatie: CONTRIBUTING, INSTALL, ROLLBACK.

## [0.1.0] - TBD

Eerste getagde release volgt na merge naar `master` en akkoord voor productie-tag.
