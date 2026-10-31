# Changelog

Alle noemenswaardige wijzigingen in dit project. Formaat: [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/),
versies volgens [Semantic Versioning](https://semver.org/lang/nl/).

## [Unreleased]

### Added

- (volgende PRs).

## [0.28.2] - 2026-10-31

### Changed

- Privacyverklaring: Belgische AVG/GBA-info (toezichthouder, klachtroute) en kort blok «Gebruik en recht»; teksten in ik-vorm.

## [0.28.1] - 2026-10-31

### Fixed

- Kaartmarkers voor aanbod op `/aanbod` en stadspagina’s: `L.divIcon` i.p.v. standaard Leaflet-icoon (unpkg `marker-icon.png`), zodat CSP `img-src` markers niet meer blokkeert na zoeken.

## [0.28.0] - 2026-10-31

### Added

- Site-brede `og:image` + `twitter:card` (bestaand logo `assets/icons/icon-512.png`, absolute URL via APP_URL).
- JSON-LD Organization op de home (naam «Gratis planten», url, logo) met `areaServed` NL én BE; zelfde areaServed op relevante CollectionPage/WebPage-schema’s.
- `<lastmod>` in `sitemap.xml` op basis van echte bestands-`mtime` / `updated_at` (niet «nu»).

### Changed

- Canonical en `og:url` altijd uit `APP_URL` (`URL::forceRootUrl`), nooit uit de request-host (voorkomt www-dubbele indexatie).
- Filter-URL’s (`?plant=`, `?plaats=` / `?type=` / `?provincie=`) canonicaliseren naar de kale pagina.
- Meta descriptions die te kort (<70) of te lang (>160) waren bijgewerkt (privacy, contact, help-mee, aanmelden, stektips, kennisbank, organisaties).

### Fixed

- `noindex` op `/account/*`; 301 van `/index.php` en `/index.php/*` naar `/` resp. het pad zonder `index.php`; geen canonical op foutpagina’s.
- Sessie-/XSRF-cookies alleen op account, beheer, feedback, CSRF-formulieren of bij bestaande sessiecookie — publieke GET’s (`/`, `/aanbod`, stadspagina’s) blijven zonder `Set-Cookie` (cachebaar).

## [0.27.3] - 2026-10-31

### Added

- 32 Belgische zadenbibs/grainothèques als deelplekken (`data/deelplekken.json`, type `zaadbibliotheek`, `country: BE`).
- `DeelplekkenSeeder` leest `country` en `visible` uit de JSON-rij (default NL / true).
- Stadspagina’s slaan JSON-rijen met `visible: false` over.
- Pest: NL-snapshot ongewijzigd, BE-country na seeden, verborgen rij niet op stadspagina.


## [0.27.2] - 2026-10-31

### Fixed

- Kaart `/aanbod` en `/deelplekken`: hoger kaartvak (360px mobiel, 480px desktop), `zoomSnap: 0.25`, minder padding, en contain-zoom (`getBoundsZoom(…, false)`) zodat heel NL+BE strak in beeld blijft (geen afsnijden; i.p.v. zoom 5 / cover).
- `public/.htaccess`: www→kaal en http→https voor `gratisplanten.nl` / `www` (vóór PHP; staging ongemoeid). Voorkomt dat een deploy handmatige prod-regels weghaalt.

## [0.27.1] - 2026-10-31

### Fixed

- Kaart op `/aanbod` en `/deelplekken`: beginview direct bij `L.map(…).fitBounds(NL+BE)`, zodat tiles en markers laden (regressie 0.27.0: `whenReady` vuurde niet zonder view).
- Zoeken (bijv. postcode `9000`): kaart zoomt rond het resultaat en niet verder uit dan NL+BE.

## [0.27.0] - 2026-10-31

### Added

- Werkgebied uitgebreid naar **heel België** (naast Nederland). Belgische postcodes 1000–9999 (4 cijfers) zijn geldig; Franstalige plaatsnamen worden overgenomen zoals de geocoder ze geeft (geen Franse sitevertaling).
- Landveld `country` (NL/BE) op accounts, deelplekken, kaartmarkers en geocode-cache; migratie zet bestaande rijen op NL. Formulieren krijgen landkeuze (default NL).
- Belgische postcodes naast NL `1234 AB`: validatie, genormaliseerde opslag en weergave per land.
- Locatiezoeken/geocoding: Nominatim met `countrycodes=nl,be` naast PDOK (NL).
- Pest: unit-tests postcode/land, migratietest `country`, feature/E2E met Belgische adressen (Gent 9000, Liège 4000, Namur 5000).

### Changed

- Kaart (aanbod + deelplekken): beginuitsnede via `fitBounds` zodat NL + heel België op desktop én mobiel in beeld zijn.
- Teksten/meta/SEO/privacy/deelplekken-H1: werkgebied «Nederland» → «Nederland en België» (geen Belgisch-juridische claims toegevoegd).

## [0.26.6] - 2026-10-31

### Removed

- Privacyverklaring: concept-banner («Concept – nog niet definitief») verwijderd.

## [0.26.5] - 2026-10-31

### Added

- Hoofdmenu-link **Dashboard** → `/beheer/overzicht` (Gate `see-admin-dashboard`), alleen bij `session('admin_ok')` of een geverifieerd account waarvan `users.email` in `stekjes.admin_dashboard_emails` staat (env `ADMIN_DASHBOARD_EMAILS`; leeg → fallback `admin_email`). Geen match op weergavenaam.
- Zelfde Gate geeft dat account toegang tot alle `/beheer`-routes zonder apart beheerwachtwoord; wachtwoord-login blijft fallback. GET `/beheer` redirect dan direct naar overzicht. Beheer-uitloggen wist alleen `admin_ok`, niet de account-sessie.
- Deploy (staging-cpanel, prod-hook, production) schrijft optioneel `ADMIN_DASHBOARD_EMAILS` naar `.env` (alleen als secret gezet, gemaskeerd); hook behoudt bestaande waarde bij lege upload.
- Beheer-gebruikers tabel: kolom **Beheertoegang** (ja/nee via `AdminDashboard::userMayAccess`, zonder e-mail tonen).

### Changed

- `AdminAuth` accepteert `admin_ok` óf Gate `see-admin-dashboard`.

## [0.26.3] - 2026-10-31

### Fixed

- Herhaalde stekjes→prod-import van dezelfde bron (nieuwe export, andere `exported_at`/package-sha) telt niets opnieuw: `import_id_maps` (bron-tabel + bron-id, voor `page_views` ook `date|path`) seedt de merge, zodat users/mail_log/geocode/page_views en overige tabellen `al_aanwezig` (of overgeslagen) worden i.p.v. opnieuw samengevoegd/toegevoegd. Idempotentie op bron-inhoud, niet op export-tijdstip. Bestaande prod-data blijft onaangeroerd (geen deletes/overschrijven).

### Changed

- `source_fingerprint` is gebaseerd op manifest table-hashes (+ files tar), zonder `exported_at`. Exact dezelfde package-sha blijft `already_imported`; een her-export met andere sha doorloopt de merge via maps zodat dry-run 0 toevoegen/optellen toont.

### Added

- Pest: twee imports met verschillende `exported_at` → tweede run alles `al_aanwezig`/overgeslagen, page_views-sum gelijk.

## [0.26.2] - 2026-10-31

### Fixed

- Samenvoeg-import herkent kopieën van de mislukte v0.24.12-run (die IDs behield en door MySQL `ALTER … AUTO_INCREMENT` al deels committe): zelfde id + dezelfde sleutelinhoud (`created_at` + tabel-specifieke sleutelvelden) → `al_aanwezig`, id→id mapping, geen verdubbeling. Zelfde id met andere inhoud blijft een echte prod-rij; bron krijgt een nieuw id.
- `page_views`: exacte id+date+path+count-kopie wordt niet opnieuw opgeteld; optellen alleen voor bronrijen die nog geen zo’n kopie zijn.

### Changed

- Response/workflow-log per tabel: extra teller `al_aanwezig` naast `prod_voor`/`bron`/`toegevoegd`/`samengevoegd`/`overgeslagen`/`prod_na`.

### Added

- Pest: gedeeltelijke eerdere import zonder verdubbeling; id-botsing behoudt prod; page_views-kopie zonder dubbele optelling; tweede run blijft `already_imported`.

## [0.26.1] - 2026-10-31

### Fixed

- Dataverhuis-import (`POST /deploy/import`): echte import faalde met HTTP 409 `PDOException` doordat dry-run alleen telde en de echte import `ALTER TABLE … AUTO_INCREMENT` *binnen* een MySQL-transactie deed (DDL = implicit commit → Laravel `commit()` faalt). Samenvoeg-import gebruikt nu nieuwe auto-increment IDs (geen ALTER in de transactie) en dry-run voert **exact dezelfde writes** uit in een rollback-transactie.
- Bij fout: veilige diagnose in JSON + `storage/logs` (SQLSTATE, driver-code, tabel, kolom; nooit waarden/e-mail/PII).

### Changed

- Import is een **samenvoeg-import** (geen replace): niets op prod wissen/truncaten/overschrijven. Records worden toegevoegd met **ID-remap** + FK-mapping. Zelfde e-mail (case-insensitive) → prod-account behouden, lege velden aanvullen, gerelateerde rijen aan prod-id koppelen. `geocode_cache`: bestaande `query_key` overslaan. `page_views`: counts **optellen** per date+path.
- Response/dry-run per tabel: `prod_voor`, `bron`, `toegevoegd`, `samengevoegd`, `overgeslagen`, `prod_na` (+ `sum_voor`/`sum_na` voor page_views). Herhaling met dezelfde package-sha → `already_imported` (geen dubbele writes/optellingen).

### Added

- Tabellen `import_runs` + `import_id_maps` voor idempotentie (package-sha + bron-fingerprint + oud→nieuw keys); migratie `2026_10_31_140000` (na evenementen/`0.26.0`).
- Export/merge omvat ook `aanbod_fotos`, `ervaringen`, `milestones_sent`, `evenementen`, `page_views` (niet: sessions/cache/jobs/migrations/import_*).
- Pest: samenvoegen met bestaande prod-data, duplicaat-e-mail, page_views optellen, tweede poging = `already_imported`, dry-run = zelfde stats zonder schrijven; 0.24.2-fixture; veilige diagnose.

## [0.26.0] - 2026-10-31

### Added

- PWA: web app manifest (`/manifest.webmanifest`, naam «Gratis planten», theme/background uit palet, iconen 192/512 + maskable, `start_url` `/`, `display` standalone, `lang` nl).
- Handgeschreven service worker (`public/sw.js`): precache app-shell met cacheversie uit `VERSION`, network-first voor HTML, cache-first voor gehashte Vite-assets; nooit account/berichten/admin, POST, `/cron`, `/deploy`, `/media` of `/media-uploads`; oude caches opruimen bij activate.
- Nederlandse offline-pagina (`public/offline.html`).
- Accountpagina: blok «Zet Gratis planten op je beginscherm» (iPhone Safari / Android Chrome) plus installatieknop via `beforeinstallprompt` — ik-vorm, geen pushmeldingen.
- Pest: manifest-route, layout-header/CSP (`worker-src`/`manifest-src`), account-installatieblok.

## [0.25.0] - 2026-10-31

### Added

- Versleutelde backups afgerond: `EncryptedBackupService` maakt DB-dump (mysqldump of PDO-fallback) + `storage/app/public` als AES-256-CBC (GPB1), met rolling retentie (`BACKUP_RETAIN_DAYS`, default 30).
- HTTP-endpoint `GET /cron/backup` (zelfde `X-Cron-Token` als `/cron`): backup draaien, `?list=1`, ciphertext downloaden (`?download=db|storage|<basename>`).
- Artisan `backup:decrypt` voor restore; workflow `backup.yml` (dagelijks) trekt staging+prod ciphertext en bewaart die 30 dagen als GitHub-artifact (off-site, zonder sleutel in logs).
- Deploy schrijft optioneel `BACKUP_ENCRYPTION_KEY` / `BACKUP_RETAIN_DAYS`; hook behoudt bestaande backup-sleutel als de upload leeg is (zelfde patroon als `APP_KEY`).
- Docs `docs/backups.md` (restore-procedure) + Pest-tests (encrypt/decrypt-roundtrip, retentie, endpoint-auth, path-traversal).
- Aanbodfoto’s: tot 3 JPEG/PNG/WebP per item (max. 8 MB), mime+decode-check, EXIF-oriëntatie, server-side herschrijven (GD of Imagick) zonder EXIF/GPS, resize/thumbnail, random namen buiten de webroot, rate-limit, media-route. Plantensoort optioneel als er een foto is. Foto’s op kaart/lijst met alt-tekst; wissen bij aanbod- of accountverwijdering (AVG); privacytekst bijgewerkt.
- Soortfoto’s op stektips-index en soortpagina’s: 48 self-hosted Wikimedia Commons-beelden met geverifieerde CC0/CC-BY/CC-BY-SA + zichtbare attribution (`data/soort-afbeeldingen.json`); neutrale SVG-fallback.
- Tests op unieke `<title>`-tags op kernroutes, soort- en stadspagina’s.
- Plantensoorten-catalogus: 337→309 zichtbaar (28 verborgen, records behouden); tag `voedselbos` (94) + API `?tag=voedselbos`; criteria in `docs/plantensoorten-selectie.md`.
- Doelgroep-landingspagina’s (niet in hoofdmenu/footer, wél in sitemap + SEO/OG/canonical/WebPage-schema): `/volkstuinen`, `/help-mee`, `/natuur`, `/verbinding` — gedeelde Blade-component `x-audience-page`, Nederlandse ik-vorm; titles/H1/CTA’s uit SEO-zoektermen + marketingadvies 31 okt.
- `/volkstuinen`: “Stekjes delen binnen je eigen volkstuin”; CTA “Zet het eerste stekje van jouw tuin online”; bestuur: QR-poster later (geen belofte dat die al bestaat); aanmelden via `/voor-organisaties/aanmelden` of contact.
- `/natuur`: “Meer groen, zonder één plant te kopen” rond inheemse/bijen-/vlinderplanten; CTA “Bied je snoeiafval aan als stek”.
- `/verbinding`: “Leer je buren kennen via een stekje”; CTA “Kijk wat je buren weggeven”.
- `/help-mee`: “Breng jij mensen graag bij elkaar?”; CTA “Meld je als verbinder: 2 uur per maand, stoppen kan altijd” via bestaand contact/feedback (geen eigen mailflow).
- Homepage-CTA “Ik help op een andere manier” linkt naar `/help-mee` (geen “binnenkort”-badge meer).
- Ervaringen delen: ingelogd lid plaatst korte tekst (max. 500), optionele foto via gedeelde `ImageProcessor` (jpeg/png/webp, max. 8 MB, finfo + decode-check, opnieuw encoden zonder EXIF/GPS, resize ~1600px), optionele koppeling soort/aanbod, plaatsnaam alleen gemeenteniveau. Status `pending` tot goedkeuring in `/beheer/ervaringen`. Publieke `/ervaringen` (noindex zolang leeg, niet in menu); homepage-blok achter `ERVARINGEN_HOME_BLOCK_ENABLED` (default uit). Lid kan eigen ervaring wissen; meegenomen in AVG-export en account-verwijdering. Privacytekst bijgewerkt.
- Overdracht afronden in gesprek (na wederzijds contact) + uitnodiging om ervaring te delen.
- Mijlpaalmail “eerste stekje” bij eerste aanbod of eerste afgeronde overdracht (Blade-template, ik-vorm). **Hard uit** via `MILESTONE_MAIL_ENABLED=false`: niets queue’en/versturen, alleen log `zou_versturen` zonder PII. Idempotent via `milestones_sent`. Badges op accountpagina (eerste stekje, 5 gedeeld, eerste ervaring) zonder punten/ranglijst.
- `/initiatieven` heeft een agenda 2026: evenementen (stekjesmarkt / plantenruilbeurs / ruildag / overig) met startdatum, optionele einddatum, plaats+provincie, organisator, externe link, bron/laatst gecontroleerd; komende items chronologisch per maand, filter op provincie/type, verlopen in inklapbaar *Geweest*, lege staat met link naar feedback; vaste ruilinitiatieven blijven als tweede sectie.
- Beheer: toevoegen/bewerken/verbergen van agenda-evenementen (geen verzonnen seed-data).
- SEO: title *Stekjesmarkt, plantenruilbeurs en ruilinitiatieven*, H1 *Plantenruilbeurzen, stekjesmarkten en initiatieven*, schema.org Event JSON-LD per komend evenement; `/initiatieven` blijft in de sitemap. Menu-label ongewijzigd.

### Changed

- `AGENTS.md` / `.env.example` / AVG-inventaris: backup-secrets en open punt (sleutel zetten) bijgewerkt.
- SEO-titels, H1’s en meta volgens keywordonderzoek (`docs/seo/doelgroep-titles-keywords.md` §2–3): home (“Gratis planten en stekjes … in je buurt”), stadspagina’s (“Gratis planten en stekjes in {Stad}”), soortpagina’s (“{Soort} stekken en gratis stekjes” / H1 “…: zo doe je het”, Geranium en Pannenkoekplant behouden), /deelplekken (plantenasiel vooraan), /invasieve-soorten (“Invasieve exoten” + echt aantal), /aanbod, /initiatieven en kennisbank-pijlers. Merk-suffix ` · Gratis planten` alleen als de title ≤60 tekens blijft (`SeoTitle`).
- Stadspagina’s tonen een blok “Plantenasiel in {Stad}” alleen als er in de data een plantenasiel mét die plaatsnaam staat (niets verzonnen); link naar `/deelplekken` gefilterd op stad + type.


## [0.24.12] - 2026-10-31

### Fixed

- Migratie: resolve-script draait via `bash` omdat /tmp op de oude server noexec is (exit 126).

## [0.24.11] - 2026-10-31

### Fixed

- `migrate-stekjes-to-prod.yml`: APP_DIR niet meer hard als sibling `stekjes-staging-app`. Optionele input `old_app_dir` (check op `artisan`); anders read-only SSH-autodetect via `scripts/resolve-old-staging-app-dir.sh` — eerst absoluut pad uit `DEPLOY_PATH/index.php` (`require …/vendor/autoload.php`, zoals `/home/…/gratisplanten-staging-app`), inclusief `__DIR__ . '/../…'`; anders precies één kandidaat onder siblings/`~/*` met artisan (nooit `app-prod`/`app-staging`). Logt gekozen APP_DIR + fingerprint VERSION/APP_URL-host.

## [0.24.10] - 2026-10-31

### Fixed

- Na release-swap blijven langlopende LiteSpeed/lsphp-processen soms de vorige release serveren (opcache/realpath via symlink `current`). Die oude release had nog `config:cache` met het oude `DEPLOY_TOKEN` → `POST /deploy/run` 403 na token-rotatie (footer toonde ook de oude versie). Hook doet nu na swap: touch van `~/.lsphp_restart.txt` (home via optionele config `home_dir`, anders `dirname(app_dir)`), `clearstatcache(true)` + `opcache_reset()` (best-effort), en wist `bootstrap/cache/config.php` + `routes.php`/`routes-*.php` in vorige releases (niet `packages.php`/`services.php`). Upload-script wacht kort op lsphp-recycle en retry’t HTTP 403 als “mogelijk stale proces” (5×, 30s).

## [0.24.9] - 2026-10-30

### Fixed

- `migrate-stekjes-to-prod.yml`: geen `environment: production` meer (env-secrets overschreven repo-SSH/DB met oude plain-PHP prod). Repo-level `DEPLOY_*` + optioneel `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`; APP_DIR altijd sibling van `DEPLOY_PATH` (niet `STAGING_APP_DIR`/cPanel). Prod-auth via repo-secret `PROD_DEPLOY_TOKEN`, BASE_URL default `https://gratisplanten.nl`. Artisan-export leest oude app-`.env` (DB-secrets niet verplicht).

## [0.24.8] - 2026-10-30

### Fixed

- Staging-deploy faalde vóór upload: verify op `public/robots.txt` in de zip gebruikte regex `(^|/)public/robots.txt$`, maar `unzip -l` toont spaties vóór de naam (geen leading `/`) → false negative + harde fail. Nu check via `zipinfo -1` / `awk '{print $NF}'`, plus expliciete `zip … public/robots.txt` na het archiveren.

## [0.24.7] - 2026-10-30

### Fixed

- `robots.txt` landde niet in de docroot na `/deploy/run` (schrijf ging vermoedelijk naar release-`public/`). Nu build-time: `scripts/write-robots-txt.sh` (staging disallow-all, productie allow + `Sitemap: https://gratisplanten.nl/sitemap.xml`) → `public/robots.txt` in de zip; hook sync’t die naar de docroot. `/deploy/run` schrijft nog secundair naar de echte docroot via `SCRIPT_FILENAME`/`index.php` en faalt met 500 + message bij mislukte write/verify. Smoke checkt dat `/robots.txt` geen hosting-fallback (“To customize…”) is. Lege stray `public/` in de docroot wordt verwijderd.

## [0.24.6] - 2026-10-30

### Added

- Dataverhuis-tooling oude SSH-staging → prod: `php artisan stekjes:export-data` (JSON/zip + optionele `files.tar`, manifest met counts/sha256; geen sessions/cache/jobs/seed-deelplekken).
- Import: `stekjes:import-data` + token-endpoints `POST /deploy/import/init|chunk|import` (alleen bij `DEPLOY_IMPORT_ENABLED=true`, zelfde `DEPLOY_TOKEN`); migrate-first, schema-drift (alleen gemeenschappelijke kolommen, `dropped_columns`), weigert non-admin users tenzij `force`, admin-merge op e-mail+zelfde id, behoud PKs/password hashes, reset auto-increment; response alleen counts.
- Fallback `stekjes:export-from-mysqldump` + client `scripts/stekjes-import-upload.sh` (chunked, geen PII in logs).
- Handmatige workflow `migrate-stekjes-to-prod.yml` (environment `production`, `confirm=VERHUIS`, `dry_run`); SSH naar oude staging, export alleen op de runner (geen artifact), daarna prod-import. `deploy-staging.yml` blijft retired/disabled.

### Changed

- `AGENTS.md` / `.env.example`: secrets en stappen voor eenmalige VERHUIS gedocumenteerd.
- `deploy-prod-hook.yml`: `workflow_dispatch` input `import_enabled` → env `IMPORT_ENABLED` → `DEPLOY_IMPORT_ENABLED` in `.env` (geen handmatige prod-`.env`-edit; tag-pushes blijven `false`).
- Migrate dry-run logt fingerprint van oude staging: `VERSION` + `APP_URL`-host (geen PII) ter verificatie van repo-level `DB_*`/`DEPLOY_*`.

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
