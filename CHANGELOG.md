# Changelog

Alle noemenswaardige wijzigingen in dit project. Formaat: [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/),
versies volgens [Semantic Versioning](https://semver.org/lang/nl/).

## [Unreleased]

### Added

- (volgende PRs) restyle / polish.

## [0.13.4] - 2026-10-29

### Fixed

- Mail opnieuw versturen (bevestiging, wachtwoord-vergeten, e-mailwijziging): max 1 per 60 s en 5 per uur per adres én per IP, met NL-wachttijdmelding en e-mailveld blijft gevuld.
- Accountmails: Nederlandse subcopy/footer; actielink één keer met `word-break: break-all` (geen dubbele URL meer).

## [0.13.3] - 2026-10-29

### Fixed

- Deploy leest `.env`-waarden via `dotenv_env_get` (strip quotes), zodat legacy-reset niet meer faalt op `APP_ENV='staging'`.
- Duidelijke foutmelding als de APP_ENV-check faalt.

## [0.13.2] - 2026-10-29

### Fixed

- Deploy schrijft `.env`-secrets als single-quoted dotenv-waarden (`scripts/dotenv-quote.sh`), zodat `#`/`!`/`$`/spaties in o.a. `MAIL_PASSWORD` niet meer worden afgekapt door phpdotenv (SMTP 535).
- Na `.env`-schrijven: `config:clear` vóór `config:cache`.

## [0.13.1] - 2026-10-29

### Fixed

- Registratie crasht niet meer (500) als SMTP faalt: account blijft bestaan, NL-waarschuwing + resend.
- Opnieuw registreren / resend voor onbevestigde accounts (zelfde wachtwoord) stuurt opnieuw een bevestigingsmail.
- Deploy schrijft `MAIL_SCHEME` (Laravel 12; `smtps`/`smtp`) i.p.v. genegeerde `MAIL_ENCRYPTION`; `MAIL_FROM` valt terug op `SMTP_USER` (Hoasted).
- Registratievalidatie toont alle fouten tegelijk.
- Gestylede Nederlandse foutpagina’s: 404, 419, 429, 500.
- Cron verwerkt database-queue (`queue:work --stop-when-empty`).
- Workflow `staging-tail-exceptions` (handmatig, geredigeerde ERROR-regels).

## [0.13.0] - 2026-10-29

### Added

- Wachtwoord vergeten / reset (Nederlandse mail, geen e-mailenumeratie).
- Wachtwoord wijzigen (huidig wachtwoord vereist; andere sessies uitloggen).
- E-mailadres wijzigen met herbevestiging: nieuw adres pas actief na link; melding naar oud adres.
- “Onthoud mij” bij inloggen.
- Nederlandse framework-vertalingen (`lang/nl`: auth, passwords, validation, pagination); `APP_LOCALE` default `nl`.
- Throttle-meldingen met wachttijd (login, registratie, wachtwoordreset, verificatie opnieuw).
- Mijn account-hub met overzicht van alle accountinstellingen.

### Changed

- Account verwijderen: wachtwoordbevestiging i.p.v. het woord “verwijder”.
- Niet-bevestigde login → duidelijk bevestigingsscherm met resend-knop.
- VERSION **0.13.0**.

## [0.12.0] - 2026-10-29

### Changed

- Zichtbare naam **Partners** → **Initiatieven** (nav, homepage, pagina, footer, kaartlegenda, meta/OG, structured data, sitemap).
- URL `/initiatieven` (canonical/sitemap); `/partners` en `/partners/*` geven **301** naar het `/initiatieven`-equivalent.
- Teksten neutraal: initiatieven waar we naar linken, geen officiële partnerschappen. Icoon `initiatief` (alias `partner` blijft).
- Kaarttype-label `partner` → zichtbaar **Initiatief**; beheerheading **Kaartmarkers**.

## [0.11.2] - 2026-10-29

### Fixed

- Menu-knop: `.nav-toggle` uit de icons `display:inline-flex`-groep gehaald zodat `@media (min-width:1200px){display:none}` niet meer overschreven wordt.
- Warn-card / safety: wit (`#FFFFFF`) met oranje linkerrand `4px #E8833A`, ink-tekst; peach `#fff1e4` verwijderd.

## [0.11.1] - 2026-10-29

### Fixed

- Header: `white-space: nowrap` op logo/nav/CTA; nav-iconen alleen in hamburgermenu; breakpoint volle nav **1200px** (Menu-knop niet parallel zichtbaar).
- `.page-title`: witte tekst op bosgroen (specifieker dan `.section h1`) — o.a. `/stijlgids` weer leesbaar.
- Waarschuwingsblok “Veilig delen”: mint/oranje-tint `#fff1e4`, ink-tekst, oranje icoon; peach/rood-bruine resten opgeruimd.

## [0.11.0] - 2026-10-29

### Added

- Hero-foto (Pexels, Anna Avilova) als WebP 640/1280/1920 met `srcset`/`sizes`.
- Iconenset: Lucide (ISC) + eigen planticonen; Blade-component `<x-icon name="…" />`.
- Interne `/stijlgids` (noindex, niet in nav/sitemap) met kleuren- en iconenoverzicht.
- `docs/LICENSE-LUCIDE.md`; licenties bijgewerkt in `docs/afbeeldingen-licenties.md`.

### Changed

- Palet: bosgroen `#1F4D2B`, mint `#E6F2E1`, oranje `#E8833A` (CTA’s met donkere tekst voor AA).
- Teal/peach/coral verwijderd uit tokens, component-CSS, SVG’s en theme-kleuren.
- Contrasttests afgestemd op het nieuwe palet; VERSION **0.11.0**.

## [0.10.0] - 2026-10-29

### Added

- Design documentatie: `docs/design-stijl.md` (palet, fonts, sectie-/knop-/kaartpatronen) en `docs/afbeeldingen-licenties.md`.
- Self-hosted webfonts **Passion One** + **Titillium Web** (OFL) onder `public/fonts/`.
- Eigen SVG-illustraties: hero-achtergrond, golfscheiders, stekje-icoon.

### Changed

- Visuele stijl naar meerbomen.nu-achtig ritme: teal/coral/peach, witte rustvlakken, pill-knoppen, display-koppen, full-bleed hero, genummerde stappen, afwisselende sectievlakken — zonder hun logo/teksten/foto’s.
- Layout en bestaande Blade-pagina’s (home, privacy, auth, account + contentpagina’s) bijgewerkt; Google Fonts CDN verwijderd.
- Theme/PWA-kleuren en contrasttests afgestemd op nieuwe tokens (WCAG AA).

## [0.9.1] - 2026-10-29

### Fixed

- Deploy schrijft `ADMIN_PASSWORD` naar `.env` (via `env:`, zelfde patroon als `CRON_TOKEN`; waarde niet gelogd).
- Beheerlogin `POST /beheer` rate-limited (`throttle:5,1`).

## [0.9.0] - 2026-10-29

### Added

- SEO-content: `/hoe-werkt-het`, `/hulpmiddelen`, `/over`, `/doe-mee` → registreren.
- Stektips-index + 16 soortpagina’s uit `data/stektips.json`; component-CSS `stektips.css`.
- `/sitemap.xml`; navigatie linkt naar stektips/hulpmiddelen/over.

## [0.8.0] - 2026-10-29

### Added

- AVG: JSON-export (`/account/export`) en account verwijderen (`/account/verwijderen`); privacyverklaring bijgewerkt.
- Beheer `/beheer` (session + `ADMIN_PASSWORD`): CRUD partnermarkers (PDOK), meldingen-afhandeling wanneer `reports` bestaat.
- Component-CSS `admin.css`.

## [0.7.0] - 2026-10-29

### Added

- Service + `/api/nu-te-stekken` voor maandkalender uit `data/nu-te-stekken.json` (Europe/Amsterdam).
- “Nu te stekken”-blok op home en aanbod (methode, tip, planten, bron); component-CSS `resources/css/nu-te-stekken.css`.

## [0.6.0] - 2026-10-29

### Added

- Tabel `partner_markers` + `/api/partners` (zichtbare markers, type-filter, afgeronde lat/lon, geen `bron`).
- Partnerspagina `/partners` (Voedselbosbouw, MeerBomen Nu + overige links); component-CSS `resources/css/partners.css`.
- Navigatie/homepage linken naar partnerspagina; kaartfilters blijven via bestaande `zoeken.js` / `aanbod.css`.

## [0.5.0] - 2026-10-29

### Added

- Beveiligd `/cron` (`X-Cron-Token`): herinneringsmails 7 dagen vóór aanbod-verloop + geocode-cache purge.
- Eenklik `/account/aanbod-verlengen?token=` (ondertekend, eenmalig); GitHub Actions daily cron wijst naar `/cron`.

## [0.4.0] - 2026-10-29

### Added

- Berichten rond aanbod: inbox, gesprek starten, chatten; e-mail/telefoon alleen na wederzijds “ja”.
- Melden en blokkeren; rate limit op berichten; `NewMessageMail` via SMTP (geen berichtinhoud, geen andermans adres).
- Component-CSS `resources/css/berichten.css`.


## [0.3.0] - 2026-10-29

### Added

- Design documentatie: `docs/design-stijl.md` (palet, fonts, sectie-/knop-/kaartpatronen) en `docs/afbeeldingen-licenties.md`.
- Self-hosted webfonts **Passion One** + **Titillium Web** (OFL) onder `public/fonts/`.
- Eigen SVG-illustraties: hero-achtergrond, golfscheiders, stekje-icoon.

### Changed

- Visuele stijl naar meerbomen.nu-achtig ritme: teal/coral/peach, witte rustvlakken, pill-knoppen, display-koppen, full-bleed hero, genummerde stappen, afwisselende sectievlakken — zonder hun logo/teksten/foto’s.
- Layout, homepage, privacy, auth- en accountpagina’s bijgewerkt; Google Fonts CDN verwijderd.
- Theme/PWA-kleuren en contrasttests afgestemd op nieuwe tokens (WCAG AA).

## [0.2.0] - 2026-10-29

### Added

- Accounts met **double opt-in**: registreren → bevestigingsmail → pas daarna inloggen/`/account`.
- Routes: `/account/registreren`, `/account/inloggen`, `/account/uitloggen`, `/account/bevestiging-nodig`, signed `/account/bevestigen/{id}/{hash}`, `/account`.
- Anti-abuse: CSRF, honeypot, minimale invultijd, rate limits, geen e-mailenumeratie, wegwerpdomeinen-lijst, wachtwoord ≥10, akkoord gratis + privacy.
- Nederlandse verificatiemail (`VerifyEmailDutch`).

### Changed

- Homepage “Doe mee”: uitdelen/ophalen linken naar registreren; helper blijft *binnenkort*.
- Navigatie: Inloggen / Doe mee of Account / Uitloggen.

## [0.1.2] - 2026-10-29

### Fixed

- Homepage **Doe mee**-paneel: conflicterende CSS zette een lichte `--card`-achtergrond terwijl kop/tekst wit bleven (onleesbaar). Donker groen paneel + lichte tekst hersteld; zichtbaar zonder JS.
- Motion: `rise`-animaties alleen bij `prefers-reduced-motion: no-preference`; basistoestand `opacity: 1` (geen opacity:0-start zonder fallback); reduced-motion forceert zichtbaarheid.
- WCAG AA: `--accent-text` / `--accent-on-dark` voor kleine “binnenkort”-labels op licht en donker.

### Changed

- Deploy rsync: `storage/app/` (en productie ook `storage/backups/`) uitgesloten van `--delete`, zodat uploads/backups op de server blijven.

## [0.1.1] - 2026-10-29

### Added

- Staging-only legacy DB-reset in `deploy-staging.yml`: bij plain-PHP schema (`schema_migrations`, of `users` zonder Laravel-migratierij `0001_01_01_000000_create_users_table`) eerst mysqldump naar `storage/backups/legacy-<timestamp>.sql.gz` (chmod 600, buiten webroot), daarna alle tabellen droppen; daarna normaal `migrate --force`. Guards: `APP_ENV=staging` + `STAGING_DB_*` secrets. Nooit in productie-workflow.

### Changed

- Staging session/cache → `file` zodat de homepage geen DB nodig heeft om te renderen (queue blijft database).
- Deploy-layout aangescherpt: app (zonder `public/`) → `APP_DIR` / `STAGING_APP_DIR`; inhoud `public/` → docroot; gegenereerde `index.php` zet `usePublicPath(__DIR__)` voor asset()/Vite. Docroot `--delete` excludes alleen `.well-known/` en `cgi-bin/`; rsync spaart `storage/backups/`.

## [0.1.0] - 2026-10-29

### Added

- Laravel 12-basis (PHP 8.2): Blade, Vite-build in CI, MySQL op server, SQLite in tests, queue/session/cache via database-tabellen (standaard Laravel-migraties).
- Gepolijste homepage: hero **Gratis planten**, waarom, Doe mee (CTAs gemarkeerd *binnenkort*), hoe het werkt, veilig delen / invasieve soorten-notitie, partners Voedselbosbouw.org en MeerBomen Nu (eigen woorden), optioneel “Nu te stekken” uit datafile, title/meta/canonical/OG + schema.org `WebSite`.
- Privacyverklaring (statische Blade-port van de AVG-tekst).
- Staging: `APP_ENV=staging` / `NOINDEX` → `X-Robots-Tag` + meta robots + `robots.txt` Disallow. Productie indexeerbaar.
- Deploy: aparte private app-map per environment; `public/` → docroot met `index.php` naar de app; `.env` uit secrets; `APP_KEY` eenmalig op de server als secret ontbreekt; `migrate --force` + config/route/view-cache; acme-challenge en rsync-excludes behouden. Productie via GitHub Environment `production` (tag `v*.*.*`).

### Changed

- Runtime van plain PHP + statische HTML-build naar Laravel. SITE_NAME / APP_NAME configureerbaar (default Gratis planten); geen hardcoded domeinen in code of workflows.
