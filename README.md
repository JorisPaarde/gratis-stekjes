# Gratis planten

Werknaam in de repo: *gratis-stekjes*. Een plek waar mensen gratis planten en stekjes aan elkaar uitdelen. Je zet in je profiel wat je over hebt, iemand uit de buurt vindt het, komt het ophalen, en het enige wat het kost is een praatje bij de voordeur.

## Neem het mee

Dit project is van iedereen. Deel het, gebruik het, kopieer het, pas het aan, begin er je eigen versie mee in je dorp, wijk of land. Je hoeft niets te vragen en niemand te noemen. Doe ermee wat je wilt.

Het doel is simpel: de wereld een stukje groener maken. Meer planten in meer tuinen, op meer balkons en in meer vensterbanken, en een beetje meer contact tussen mensen die bij elkaar in de buurt wonen.

## Het idee

- **Gratis.** Niets wordt verkocht. Geven en ophalen, meer niet.
- **Van mens tot mens.** Zoeken op postcode of plaats, bericht via de site; contactgegevens pas na wederzijds akkoord.
- **Privacy eerst.** Op de kaart alleen een benaderde plek, nooit een adres of e-mailadres.
- **Groen en eerlijk.** Invasieve soorten in Nederland horen er niet tussen.

## Status (v0.15.0)

Laravel 12: accounts (registratie/double opt-in, login met onthoud mij, wachtwoord reset/wijzigen, e-mailwijziging met herbevestiging, AVG-export/wissen), aanbod met controle op invasieve Unielijst-soorten, berichten, expiry-cron, initiatieven (`/initiatieven`, oude `/partners` → 301), deelplekken (`/deelplekken`), stadspagina’s (`/gratis-planten`), nu-te-stekken, beheer, stektips + contentpagina’s + `/invasieve-soorten` + sitemap. Visuele stijl met bosgroen/mint/oranje-palet, hero-foto en iconenset (eigen teksten/beelden).

## Stack

- Laravel 12, PHP 8.2+, Blade, Vite (build in CI — geen Node op de server)
- MySQL op staging/productie, SQLite in tests
- Mail via SMTP; queue via database; staging session/cache: file (homepage DB-onafhankelijk)

Weergavenaam: `SITE_NAME` / `APP_NAME` (default **Gratis planten**). Domein alleen via `APP_URL`.

## Zelf draaien

```sh
composer install
cp .env.example .env && php artisan key:generate
# SQLite: touch database/database.sqlite en DB_CONNECTION=sqlite
php artisan migrate
npm ci && npm run build
php artisan serve
```

Uitgebreider: [`docs/INSTALL.md`](docs/INSTALL.md). Branches: [`CONTRIBUTING.md`](CONTRIBUTING.md). Rollback: [`docs/ROLLBACK.md`](docs/ROLLBACK.md).

### Deploy

CI: `composer install --no-dev --optimize-autoloader` en `npm ci && npm run build`.

- **Docroot** = `DEPLOY_PATH` / `STAGING_DEPLOY_PATH` (cPanel main domain; niet verplaatsbaar)
- **App** = `APP_DIR` / `STAGING_APP_DIR` (buiten webroot; staging default sibling `stekjes-staging-app`)
- Rsync app **zonder** `public/` → app-map; inhoud `public/` → docroot
- Gegenereerde `index.php` in de docroot laadt de app en zet `usePublicPath(__DIR__)` (Vite/asset)
- Docroot `--delete` excludes: `.well-known/`, `cgi-bin/`

Secrets en paden alleen in GitHub Secrets / Environments. Productie: tag `v*.*.*` in environment `production`. Staging: push naar `staging`.

## Licentie

- **Code:** [The Unlicense](LICENSE).
- **Teksten en inhoud:** [CC0 1.0](LICENSE-CONTENT.md).

Bronnen (zoals de EU-lijst van invasieve soorten) houden hun eigen voorwaarden.
