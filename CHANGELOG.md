# Changelog

Alle noemenswaardige wijzigingen in dit project. Formaat: [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/),
versies volgens [Semantic Versioning](https://semver.org/lang/nl/).

## [Unreleased]

### Added

- (volgende PRs) restyle / polish.

## [0.20.1] - 2026-10-29

### Added

- Contactpagina `/contact` + footerlink; sitemap en config `site.contact_email` (default `info@gratisplanten.nl`).

### Changed

- Privacy: verwerkingsverantwoordelijke met openbare JP Web Creation-gegevens (adres, KvK, btw, telefoon) van jpwebcreation.nl; concept-banner zonder placeholders; sitecontact overal `info@gratisplanten.nl` (geen `info@jpwebcreation.nl` in views); mail-from default idem.

## [0.20.0] - 2026-10-29

### Added

- SEO-pijlers op `/stektips`: Stekken, Snoeien (+ snoeikalender), Natuurlijk bewortelen, Permacultuur, Vermeerderen overig — afgestemd op `docs/seo/stektips-keywords.md`.
- Top-pagina’s: hortensia/vlinderstruik/lavendel/olijfboom/rozen/vijg snoeien (+ stekken waar geen soortpagina); pijlers stekken & snoeien; monstera stekken in water; stekpoeder↔wilgenwater via pijler Natuurlijk bewortelen.
- Verplichte interne links: snoei→stek, stek→wilgenwater + `/aanbod?plant=X`; feature-test `KennisbankSeoLinksTest`.
- Aanbod: query `plant` filtert op aanbodtekst (server-side + API); `q` blijft postcode/plaats.

### Changed

- Kennisbank-categorieën hernoemd naar de vijf pijlers; bestaande artikelslugs ongewijzigd (geen breaking redirects).
- Soortpagina’s hortensia/lavendel/monstera/vijg dienen als stekpagina (geen dubbele content).
- Kennisbank-artikelen in ik-vorm (geen we/wij/ons/onze); test dekt `data/kennisbank/*.json`, `soorten/*.json` en `data/stektips.json`.

## [0.19.1] - 2026-10-29

### Changed

- Privacy (`/privacy`): volledige concept-AVG-verklaring op basis van wat de app doet (accounts, aanbod, berichten, organisatie-aanmeldingen, sessies/rate-limits, geocode-cache, SMTP/Hoasted, retentie/cron, export/wissen); zichtbaar gemarkeerd als **Concept – nog niet definitief**; KvK/adres als `[in te vullen door Joris]`.
- Over (`/over`): wie (Joris), waarom (delen i.p.v. weggooien/kopen), hoe het werkt, gratis en zonder reclame.
- Initiatieven (`/initiatieven`): feitelijke korte beschrijvingen i.p.v. “Volgt binnenkort” (o.a. Stekjesroute, Velt, Zadenbibliotheek, Voedsel uit het Bos, Netwerk Voedselbosbouw, Permacultuur Nederland, Eetbaargoed).
- Feature-test faalt als “Volgt binnenkort” nog in views/data/soorten staat (inclusief stektips-data); lege stektip-rijen worden verborgen i.p.v. die placeholder.
- Privacy, over en initiatieven in strikte ik-vorm (geen we/wij/ons/onze); test controleert dat op die drie pagina’s.

## [0.19.0] - 2026-10-29

### Added

- Kennisbank op `/stektips`: 11 artikelen (JSON in `data/kennisbank/*.json`) over stekmethoden, timing, wortelbevordering, snoeien en permacultuur — elk met ≥1 geverifieerde bron-URL.
- Artikelpagina’s op `/stektips/artikel/{slug}` (padsegment `artikel` voorkomt botsing met soort-slugs; zie `docs/kennisbank-urls.md`).
- Overzicht `/stektips` met server-side zoeken en filters (categorie, seizoen, maand, binnen/buiten, soort); 48 soortpagina’s blijven op `/stektips/{slug}` en zijn filterbaar.
- Breadcrumbs, schema.org Article/HowTo JSON-LD, blok “Gerelateerde artikelen” (ook op soortpagina’s); stekpoeder verwijst naar wilgenwater als duurzamer alternatief.
- Sitemap uitgebreid met kennisbank-URL’s; tests voor filter/zoek/artikel/related/bronnen.

## [0.18.0] - 2026-10-29

### Added

- Stektip-velden gevuld voor alle 48 soorten (geen lege tipvelden / geen “Volgt binnenkort” meer op soortpagina’s); per soort ≥1 geverifieerde bron (o.a. RHS, WUR, Vlaco).
- Unit-test `StektipsCompletenessTest`: faalt bij lege tipvelden, placeholdertekst of ontbrekende bron-URL; controleert sync `soorten/*.json` ↔ `data/stektips.json`.

### Changed

- Route-audit: monstera toont nu Bronnen (gevuld); Ervaringen blijven verborgen zolang leeg.

## [0.17.2] - 2026-10-29

### Added

- Route-audit feature test: alle publieke routes + soort-/stad-/organisatiepagina’s (leeg) en account/beheer (gevuld via `DemoSeeder`) moeten HTTP-status onder 500 blijven; inclusief `/organisaties/{slug}` onder `config:cache` + `route:cache` met `APP_DEBUG=false`.
- `AanbodFactory` + `DemoSeeder` voor lokale gevulde audits (gebruikers, aanbod, berichten).
- Auditrapport `docs/audit-2026-10-29.md` + helper-scripts `scripts/audit-crawl.php` en `scripts/audit-mobile.mjs`.

### Changed

- Stektips: “Joris' eigen proef” linkt naar `/stektips/herfstolijfwilg`; Bronnen gevuld vanuit `nu-te-stekken` waar beschikbaar; lege Ervaringen/Bronnen-rijen worden verborgen i.p.v. “Volgt binnenkort”.
- Vriendelijkere empty states voor zoeken zonder treffers, mijn aanbod, inbox en beheer-lijsten.
- Mobiel: min. 44px touch targets op knoppen/nav; tabellen scrollen horizontaal i.p.v. layout te breken; overflow-x op body begrensd.
- `DatabaseSeeder` seeded alleen nog verplichte deelplekken (demo-data via `DemoSeeder`).

### Fixed

- Route-audit: `/beheer` zonder `ADMIN_PASSWORD` is bewust HTTP 503 — test verwacht dat expliciet i.p.v. generiek <500 (CI faalde daarop).

## [0.17.1] - 2026-10-29

### Fixed

- `/organisaties/{slug}` gaf HTTP 500 als een organisatie geen `deelplek_slugs` had (o.a. Velt): de view riep `isNotEmpty()` aan op een lege PHP-array i.p.v. een Collection. Controller geeft nu altijd een Collection; feature-test dekt alle slugs met `config:cache` + `route:cache` en `APP_DEBUG=false`.

## [0.17.0] - 2026-10-29

### Added

- Uitnodiging voor organisaties: publieke pagina `/voor-organisaties`, aanmeldformulier (honeypot + rate limit + PDOK-geocoding), pending → admin goedkeuren/afwijzen; pas na goedkeuring zichtbaar op `/deelplekken` en stadspagina’s.
- Bevestigingsmail aan de aanmelder + admin-melding via SafeMail (SMTP); geen mail naar derden buiten de aanmelder.
- Organisatiepagina’s (`/organisaties`, `/organisaties/{slug}`) met korte feitelijke tekst en bronlink; koppeling naar deelplekken waar van toepassing.
- `docs/uitnodigingen.md`: contactpunten + concept-uitnodigingstekst — **alleen voorbereiding, niets versturen**.
- Deelplek-types `voedselbos` en `tuinvereniging`; footer- en deelplekken-links naar voor organisaties.

## [0.16.0] - 2026-10-29

### Added

- Stektips uitgebreid met ~32 sier- en kamerplanten (o.a. monstera, pothos, sanseveria, pilea, vetplanten, geranium, fuchsia, hortensia, lavendel); licht/water, SEO-titel, verwante soorten, link naar aanbod.
- Feature tests: elke soortpagina 200 + sitemap; geen EU-Unielijst-soorten in stektips (via `InvasieveSoorten` / `data/niet-aanbieden.json`).

## [0.15.4] - 2026-10-29

### Added

- Eigen aanbod verwijderen vanaf `/account/aanbod` (knop **Verwijderen**, CSRF + `confirm('Weet je het zeker?')`, flash “Aanbod verwijderd.”). Verwijderde items verdwijnen uit zoek-API, kaart en stadspagina’s.

## [0.15.3] - 2026-10-29

### Fixed

- Transactionele account-mails (registratie/bevestiging, reset, e-mailwijziging) gaan via `SafeMail` na de HTTP-response (`app()->terminating`), niet via de database-queue — geen wachten op SMTP en geen afhankelijkheid van cron.
- `cron.yml` pingt staging én productie elk apart, elke 15 minuten (was 1×/dag met fallback die staging overslaat).


## [0.15.2] - 2026-10-29

### Fixed

- Deelplekken worden bij staging- en productie-deploy idempotent geladen (`db:seed --class=DeelplekkenSeeder --force` na migrate), zodat `/deelplekken` niet leeg blijft na een verse deploy.

## [0.15.1] - 2026-10-29

### Fixed

- `/account/aanbod` gaf HTTP 500: Blade liet `@endif` plakken aan `verborgen` zonder woordgrens, waardoor de template niet compileerde.
- Aanbod plaatsen via `POST /account/aanbod/nieuw` (`account.aanbod.store`); oude `POST /account/aanbod` blijft werken.

## [0.15.0] - 2026-10-29

### Added

- SEO-stadspagina’s **Gratis planten in [stad]** (`/gratis-planten/{slug}`) voor ~40 grootste gemeenten, plus overzicht `/gratis-planten` per provincie.
- Brondata in `data/steden.json` (CI-validatie); per stad unieke intro, actueel aanbod binnen straal, kaartuitsnede, deelplekken in/bij de stad (uit `data/deelplekken.json`), nabije steden, FAQ + FAQPage-schema en canonical.
- Sitemap-, footer- en homepage-link (“Gratis planten in jouw stad”).


## [0.14.1] - 2026-10-29

### Fixed

- E-mailwijziging: bevestigingsmail gaat naar het nieuwe/pending adres (`Notification::route`), niet naar het oude via `$user->notify()`.
- Accountmails (bevestiging, reset, e-mailwijziging, melding oud adres) staan op de database-queue (`ShouldQueue`); cron verwerkt `queue:work` al elke minuut.
- Data-export en site lezen versie uit het `VERSION`-bestand (geen hardcoded `config/site.php`-string meer).

## [0.14.0] - 2026-10-29

### Added

- Landelijk overzicht **deelplekken** (`/deelplekken`): plantenasiels, plantenbiebs, ruilkasten, stekjesruilpunten en zaadbibliotheken, met filter op type/provincie/plaats, bronlink per plek en markers (ruit-icoon) op de aanbodkaart.
- Brondata in `data/deelplekken.json` (CI-validatie) + migratie/seeder `deelplekken`.
- Sitemap, navigatie, footer en homepage-link; SEO-title/description over plantenasiel/plantenbieb/ruilkast bij jou in de buurt.

## [0.13.5] - 2026-10-29

### Added

- Controle op invasieve Unielijst-soorten bij aanbod plaatsen en wijzigen (server-side; optionele client-side tip tijdens typen).
- Uitlegpagina `/invasieve-soorten` met de 47 NVWA terrestrische + water-/oeverplanten uit `data/niet-aanbieden.json`, in sitemap en footer.
- Aanbod wijzigen (eigen items) met dezelfde validatie als plaatsen.

### Changed

- Homepage “Veilig delen”: link naar de lijst i.p.v. “Lijst binnenkort”.
- VERSION **0.13.5**.

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
