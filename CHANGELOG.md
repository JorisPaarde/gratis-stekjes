# Changelog

Alle noemenswaardige wijzigingen in dit project. Formaat: [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/),
versies volgens [Semantic Versioning](https://semver.org/lang/nl/).

## [Unreleased]

### Added

- (volgende PRs).

## [0.24.5] - 2026-10-30

### Fixed

- Pre-prod docroot: `DirectoryIndex index.php` in `public/.htaccess` zodat cPanel’s `index.html` niet wint van Laravel; deploy-hook verwijdert alleen de cPanel-placeholder `index.html` (“Domein gereserveerd” / niet uit `public/`), behoudt `.user.ini`/`php.ini`/`cgi-bin` en overschrijft een verse `robots.txt` niet.
- Hosting server-wide `robots.txt` schaduwde de Laravel-route: `POST /deploy/run` schrijft nu de env-aware app-output (staging disallow-all, productie allow + sitemap) als statisch `robots.txt` in de docroot.
- `deploy-hook-upload.sh`: retry met backoff (5×, 5s/10s/…) op `POST /deploy/run` bij 404/5xx/connectie na release-swap (opcache/fs-timing).

## [0.24.4] - 2026-10-30

### Added

- HTTPS deploy-hook voor cPanel noshell (geen `:2083`/UAPI): `deploy/hook/deploy-hook.php` + `lib.php`, config buiten docroot (`~/.deploy/<env>.php` met token-hash), chunked upload (5 MiB + sha256), release-swap met behoud van `storage/` en `.env`, daarna `POST /deploy/run` + smoke.
- `scripts/deploy-hook-upload.sh` (artefact-zip, `.env` via `dotenv-quote.sh`, Bearer-upload, migrate, smoke; duidelijke `::error::` + INSTALL-stappen bij hook-404).
- `scripts/make-deploy-hook-config.sh` (random token → `gh secret set DEPLOY_HOOK_TOKEN`, print alleen config met hash).
- Workflow `deploy-prod-hook.yml` (tags `v*.*.*` / `workflow_dispatch`, environment `production`, secrets `PROD_*`); draait niet op merges naar staging.
- `deploy/hook/INSTALL.md` (eenmalige cPanel File Manager-stappen) + Pest/self-test voor auth, chunks/checksums en storage-behoud.

### Changed

- `deploy-staging-cpanel.yml` gebruikt alleen HTTPS-hook (geen force-push `deploy-staging`, geen UAPI-retries). Branch `deploy-staging` blijft bestaan maar wordt niet meer aangestuurd.
- Docs (`AGENTS.md`, `README`, `docs/INSTALL.md`, `CONTRIBUTING`): secretnamen en flow bijgewerkt; `CPANEL_*` legacy.

## [0.24.3] - 2026-10-30

### Added

- Staging-deploy via cPanel Git Version Control + UAPI (workflow `deploy-staging-cpanel.yml`): build `vendor` + `public/build`, force-push naar branch `deploy-staging` met gegenereerde `.cpanel.yml` (niet op `staging`/`master`), `VersionControl::update`/`VersionControlDeployment`, `.env` via Fileman, `POST /deploy/run` (Bearer `DEPLOY_TOKEN`, hash_equals, rate limit, alleen staging of `DEPLOY_ENDPOINT_ENABLED`), smoke (200/noindex/versie).
- `scripts/cpanel-deploy.sh` (handmatig / vaste machine bij Imunify-blokkade op `:2083`) met retries/backoff en duidelijke fout bij 403/non-JSON; `scripts/cpanel-deploy-staging.sh` voor cPanel deploy-tasks (app buiten docroot, `public/` → docroot, behoud `.env`/storage/uploads).
- 301 van `gratisstekjes.nl` / `www.gratisstekjes.nl` → `https://gratisplanten.nl` (pad+query), middleware + `.htaccess`; uit op staging/local/testing.
- `AGENTS.md` met cPanel-instellingen en GitHub secretnamen. Pest voor deploy-endpoint en canonical-host.

### Changed

- Oude SSH `deploy-staging.yml` retired: alleen `workflow_dispatch` + `ALLOW_LEGACY_SSH_STAGING=1` (anders hard fail). Environment `staging` deelt nu gratisplanten-DB/URL — actieve route is `deploy-staging-cpanel.yml`.
- cPanel-secrets: exact `CPANEL_HOST`/`USER`/`API_TOKEN`, `DEPLOY_TOKEN`, `STAGING_BASE_URL`, `STAGING_DB_*`; repo `SITE_NAME`/`SMTP_*`/`CRON_TOKEN`/`ADMIN_PASSWORD`. Geen `APP_KEY`-secret. Serverpaden vast in workflow/script. Noshell: `POST /deploy/run` is leidend voor migrate/cache.

## [0.24.2] - 2026-10-30

### Changed

- Feedbackknop: zachte bounce-animatie (2–3× bij laden, hoogstens één herhaling na ~20s, daarna stil; uit bij `prefers-reduced-motion`); tip/speech-bubble op hover en toetsenbordfocus (“Gratis planten is nog in opbouw…”) met `aria-describedby`; op touch één keer per sessie ~5s zichtbaar (sessionStorage, wegklikbaar), safe-area en geen horizontale scroll op 390px.
- Feedbackformulier: kalmere card-layout (label, spacing, primair + Terug); copy zonder “geen mail / geen tracking”. Privacy-regel over feedback eveneens zonder die formulering; overige privacy-tekst ongewijzigd.
- Beheer `/beheer/feedback`: alleen kaarten op alle schermbreedtes (geen tabel/kolommen); meta (datum Europe/Amsterdam + pad), leesbare tekst, statusbadge nieuw/gelezen, “Markeer gelezen”; ongelezen visueel duidelijker; max. leesbreedte op desktop.

### Added

- Pest: formulier zonder “tracking”; beheer zonder `<table>`; knop met tip/`aria-describedby`.

## [0.24.1] - 2026-10-30

### Fixed

- Beheer-feedback op smal scherm (`<640px`): items als kaarten (datum + pad op één regel, volle tekst met normale wrapping, status + “Markeer gelezen” onderaan) i.p.v. een geperste tabelkolom; vanaf 640px blijft de tabel.

## [0.24.0] - 2026-10-30

### Added

- Feedback voor ingelogde leden: zwevende knop (rechtsonder, safe-area, toetsenbordtoegankelijk) op publieke en accountpagina’s (niet voor gasten, niet op `/beheer`); formulier op `/feedback` (werkt zonder JS) met textarea, verborgen `page_path`, CSRF + honeypot, rate-limit 5/uur/lid. Succesmelding: “Dank je, ik lees alles zelf.” Geen mail, geen tracking, geen extra cookies.
- Tabel `feedback` (`user_id` nullable FK `nullOnDelete`, `page_path`, encrypted `body` zoals berichten, `read_at`, timestamps).
- Beheer `/beheer/feedback` (admin-auth, noindex): nieuwste eerst, datum Europe/Amsterdam, pad + ontsleutelde tekst, zonder naam/e-mail; markeer gelezen. Link vanaf beheer-overzicht.
- Statistieken-KPI “Feedback” (totaal + ongelezen, sparkline, link naar `/beheer/feedback`).
- Privacy: korte regel over feedback; bij accountwissen blijft feedback geanonimiseerd.
- Pest: gast ziet geen knop / kan niet posten; lid kan indienen (body encrypted in DB); rate-limit; admin toont plaintext; gast redirect van beheer; dashboard toont count.

## [0.23.3] - 2026-10-30

### Fixed

- Beheer-statistieken KPI-grid: `align-items: start` zodat een opengeklapte kaart buren niet meerekkt; op smal scherm (`≤719px`) neemt een open kaart de volle rij.
- Overzichtsgrafiek x-as: labels met minimale tussenruimte; laatste datum blijft, overlappende voorganger valt weg.

## [0.23.2] - 2026-10-30

### Changed

- Beheer-statistieken (`/beheer/statistieken`) visueler: KPI-kaarten met groot cijfer + inline-SVG sparkline, uitklapbare SVG-charts (`<details>`), overzichtsgrafiek (aanmeldingen + bezoekers, 30 dagen) standaard open; donuts voor bevestigd/onbevestigd en leden-met-aanbod; horizontale staven voor top pagina’s. Geen externe chart-libs/CDN/JS. Tabellen geminimaliseerd (per-dag pageviews alleen in ingeklapte sectie).
- Lidmetrics: **Leden** (`users` count) en **Leden die iets aangeboden hebben** (`COUNT(DISTINCT aanbod.user_id)` — ooit ≥1 listing, elke status). Geen uitdelers/ophalers-split, geen ophaalmeting.
- Site-copy: één accounttype — homepage Doe-mee, registreren, hoe-werkt-het en over spreken niet meer van aparte uitdeler-/ophaler-accounts of rolkeuze bij registratie. Listing-type uitdelen/ophalen blijft (wat je plaatst), geen accountrol.

### Added

- Pest: statistieken renderen met SVG-charts (admin), lege data zonder fouten, guest redirect; homepage zonder `rol=`-links.

## [0.23.1] - 2026-10-30

### Changed

- Beheer-statistieken (`/beheer/statistieken`) opnieuw vormgegeven in huisstyle: KPI-kaarten in responsive grid (incl. bezoekers vandaag), CSS-staafdiagrammen voor aanmeldingen (30 dagen) en pageviews (14 dagen), zebra-tabellen met uitgelijnde aantallen, collapsible per-dag-breakdown, mobile-first (2 kolommen op smal scherm, tabellen scrollen in eigen container). Geen externe chart-libs/CDN/JS. Auth, noindex en privacy (alleen aggregaten) ongewijzigd.

## [0.23.0] - 2026-10-30

### Added

- Beheer-statistiekenpagina `/beheer/statistieken` (zelfde admin-sessie als `/beheer`): aggregaten voor aanmeldingen (totaal + per dag, 30 dagen met CSS-balken), bevestigd/onbevestigd, uitdelers vs ophalers (uitdeler = ≥1 aanbod; anders ophaler/alleen-account), aanbiedingen (totaal + actief), berichten, en pageviews. Alleen aggregaten — geen namen of e-mailadressen. noindex (meta + `X-Robots-Tag`); niet in sitemap; link vanaf beheer-overzicht.
- Privacy-vriendelijke paginateller: tabel `page_views` (`date`, `path`, `count`, unique date+path), middleware op GET HTML 200 voor publieke webroutes. Geen cookies/sessie/IP/UA/querystring; pad genormaliseerd en begrensd; bot-UA en `/beheer*`, `/account*`, assets, robots/sitemap uitgesloten. DB-fouten worden gelogd zonder de pagina te breken.
- Pest-tests voor admin-auth, aggregaten zonder PII, teller-increment, uitsluitingen, bot-UA en geen Set-Cookie door de teller.

## [0.22.3] - 2026-10-29

### Added

- `mail_log`-tabel + model: na elke SafeMail-verzendpoging een regel (`type`, `ok`/`fail`, korte `safeSummary`-fout, optioneel `user_id`). Geen e-mailadres of andere persoonsgegevens.
- Beheer: per gebruiker bevestigd ja/nee, laatste mailstatus (type/ok-fout/tijdstip Europe/Amsterdam) en knop “Bevestigingsmail opnieuw sturen” (POST+CSRF, admin, rate limit).
- Cron purge van `mail_log` ouder dan 90 dagen (AVG-retentie).
- Pest-regressie: dotenv-quote rondtrip voor waarde met `#` (`QUOTE_TEST_VALUE=voor#na`).

### Changed

- E-mailverificatie geldig 24 uur (`auth.verification.expire` 60 → 1440); bevestigingsmail noemt die geldigheid.
- Deploy `.env`-schrijven (`scripts/dotenv-quote.sh`): double-quoted waarden met escape van `\`, `"` en `$` (i.p.v. single quotes), zodat `#`/spaties/`=`/`'` in secrets (o.a. `MAIL_PASSWORD`) correct blijven.

### Fixed

- SMTP-fouten na registratie blijven zichtbaar via `mail_log` / beheer (niet alleen `laravel.log`).

## [0.22.2] - 2026-10-29

### Fixed

- `/account/aanbod`: empty-check via `count()` (array- en Collection-veilig); Blade-syntax blijft zonder aan-tekst geplakte `@endif` (regreszie van staging-log 17:34).
- `/organisaties/{slug}`: controller geeft altijd een Support Collection; view gebruikt `count($deelplekken)` i.p.v. `isNotEmpty()` (voorkomt 500 bij kale array, staging-log 18:09).
- Zelfde Collection/array-veilige `count()`-checks in inbox- en beheer-views; mailtemplate expiry zonder aan woord geplakte `@if`.

### Added

- CI + Pest: `php artisan view:cache` compileert alle Blade-views (syntaxfouten falen in CI).
- Feature-tests: `/account/aanbod` met/zonder aanbod; organisatiepagina met/zonder deelplekken.

## [0.22.1] - 2026-10-29

### Changed

- Plantensoort-zoekranking: exacte naam → naam begint-met → synoniem/Latijn begint-met → bevat (bijv. `monst` zet Monstera vóór Apenmaskerplant).
- Autocomplete: bij openen geen optie gemarkeerd; eerste ArrowDown markeert de eerste; Enter zonder markering laat vrije invoer staan.
- Synoniem-treffer toont het synoniem in de suggestie (`match`-veld in `/api/plantensoorten`).
- Sitetekst in ik-vorm (o.a. `/account/bevestiging-nodig`: “Ik heb je … gestuurd”); test faalt op zichtbaar “We hebben” / “wij” in views.

## [0.22.0] - 2026-10-29

### Added

- Autocomplete voor plantensoort bij stekje aanbieden: catalogus `data/plantensoorten.json` (48 stektips + gangbare NL tuin-/kamer-/moestuinplanten), JSON-zoekendpoint `/api/plantensoorten` (named rate limit 60/min per IP), toegankelijke combobox (WAI-ARIA), optionele koppeling `aanbod.plantensoort_id` (suggestie of exacte server-side match op naam/synoniem/Latijn; vrije invoer blijft mogelijk). Vite-JS + fetch naar eigen domein (CSP `connect-src 'self'`; geen externe scripts behalve bestaande unpkg-toelating).
- Pest-tests voor endpoint, koppeling, vrije invoer, invasieve check, rate limit en CSP-compatibiliteit.

### Changed

- Aanbodformulier: plantensoort-veld met progressive enhancement (zonder JS gewoon tekstveld); invasieve-soortencheck dekt gekozen suggesties, synoniemen, Latijnse namen en vrije invoer.

## [0.21.0] - 2026-10-29

### Added

- AVG-inventaris (`docs/avg-inventaris.md`) op basis van code/migraties/config, met gaten gemarkeerd.
- Security-headers middleware: HTTPS-afdwingen op staging/productie, HSTS (≥1 jaar + includeSubDomains), CSP, X-Content-Type-Options, Referrer-Policy, X-Frame-Options, Permissions-Policy; sessiecookies secure+httponly+samesite.
- Versleutelde opslag (Laravel encrypted casts) voor berichtinhoud, telefoon, deelplek-contact-e-mail en meldingsreden; e-mail blijft plaintext (login/uniek) — gemotiveerd in inventaris.
- Log-processor `MaskPersonalDataProcessor` maskeert e-mail/IP in logs.
- Artisan `backup:database-encrypted` + `docs/backups.md` (server-cron/off-site opslag blijft open punt).
- Rate-limit op aanbod plaatsen; throttle op wachtwoord-reset-POST.

### Changed

- Privacyverklaring sluit aan op inventaris (gegevens, grondslag, cookies, derden, rechten met links naar export/wissen); JP Web Creation + KvK/btw + concept-banner; ik-vorm. Geen privéadres/telefoon op de site (in lijn met 0.20.3).
- Account wissen ruimt ook password-reset-tokens en sessies op; export bevat `fotos[]` (leeg zolang geen upload) en ontsleutelde berichten via Eloquent.
- Deelplek-aanmelding slaat geen straatadres op (alleen postcode-4 + plaats; coords afgerond).

## [0.20.3] - 2026-10-29

### Changed

- Privéadres (Bernadottelaan 273, 2037 GR Haarlem) en telefoon (06 21 22 88 84 / tel:+316…) verwijderd van privacy en overal elders; KvK 90397320, btw NL004813310B95, JP Web Creation en `info@gratisplanten.nl` blijven.
- Footer toont KvK en btw (zichtbaar op homepage en overige pagina’s).

### Added

- Pest-test scant views/content-JSON/config op Bernadottelaan en privételefoon-schrijfwijzen; HTTP-test checkt `/` en `/privacy`.

## [0.20.2] - 2026-10-29

### Fixed

- Permacultuur-pillarkaart op `/stektips` (hub `/stektips/artikel/permacultuur-gilde-voedselbos`); test dwingt alle vijf pijlers af.

### Changed

- Snoeikalender op pijler Snoeien: twaalf maandregels i.p.v. zes groepen.

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
