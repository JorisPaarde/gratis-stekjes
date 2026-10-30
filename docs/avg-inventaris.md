# AVG-inventaris (Gratis planten)

Gebaseerd op code, migraties en config in deze repo (versie `VERSION`).  
**Status:** concept voor Joris — gaten gemarkeerd als **GAT**.  
Contactadres site: `info@gratisplanten.nl` (`config('site.contact_email')`).

## Overzicht per gegevenscategorie

| # | Gegeven | Wat / waar | Waarom | Grondslag | Bewaartermijn | Wie inziet | Beveiliging |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | **Account** | Tabel `users`: `name`, `email` (uniek), `password` (hash), `active`, `email_verified_at`, `pending_email`, `remember_token`, timestamps | Account, login, e-mailbevestiging | Toestemming (registratie + privacy-akkoord) | Zolang account bestaat; inactief-beleid `stekjes.inactive_account_months` (default 12) — **GAT: automatische purge niet gebouwd** | Eigenaar (account); beheerder alleen via DB/SSH (geen user-UI in `/beheer`) | Wachtwoord: Laravel `hashed` cast (bcrypt default); e-mail **niet** versleuteld (login + unique index) |
| 2 | **E-mail** | `users.email`, `users.pending_email`; `password_reset_tokens.email` | Login-identiteit, mails, reset | Toestemming / uitvoering overeenkomst | Account: zie #1; reset-tokens: Laravel default (~60 min, `config/auth.php`) | Eigenaar; SMTP-provider bij verzending | Plaintext in DB (nodig voor lookup). Motivatie: encryptie breekt unique + Auth. Logs maskeren e-mail (`MaskPersonalDataProcessor`) |
| 3 | **Telefoon** | `users.telefoon` (optioneel) | Alleen zichtbaar bij wederzijds “ja” op contact in gesprek | Toestemming | Tot account wissen of leegmaken in profiel | Eigenaar; wederpartij bij wederzijds contact | **Encrypted cast** at rest |
| 4 | **Locatie / postcode** | `users.locatie_label` (postcode-4 of plaats), `lat`/`lon` afgerond (`COORD_DECIMALS`, default 3 ≈ 100 m) via `Geo::roundCoord` / `PdokGeocoder` | Kaart/zoeken nabijheid | Toestemming | Tot account wissen / profielwijziging | Publiek: label + afgeronde punt op kaart/API (geen e-mail/tel) | Geen straat/huisnummer via site; geen exacte coords in opslag |
| 5 | **Berichten** | `messages.body`, `conversations` (+ `contact_a`/`contact_b`), `blocks`, `reports.reason` | Onderlinge afspraak planten ophalen/uitdelen; moderatie | Toestemming; gerechtvaardigd belang (misbruik) bij meldingen | Tot account wissen (cascade/expliciete delete in `AvgService`) — **GAT: geen aparte retentie “X maanden na laatste bericht”** | Deelnemers gesprek; beheerder: meldingen in `/beheer` | `body` + `reason` **encrypted**; mail “nieuw bericht” zonder inhoud/adres ander |
| 5b | **Feedback** | Tabel `feedback`: `body` (encrypted), `page_path`, `user_id` (nullable), `read_at` | Productverbetering; geen mail | Toestemming | Bij accountwissen: `user_id` → null (geanonimiseerd, `nullOnDelete`); tekst blijft voor beheer | Beheer `/beheer/feedback` (geen naam/e-mail); eigenaar via export | `body` **encrypted** (zelfde cast als berichten); rate-limit 5/uur/lid |
| 6 | **Aanbod** | Tabel `aanbod`: type, tekst, visible, expires, renew_token | Publiek zoeken/kaart | Toestemming | TTL `aanbod_ttl_days` (60); verlengbaar; cron-herinnering | Publiek (zichtbaar+niet verlopen); eigenaar in account | Geen persoonsgegevens in API-payload (`ZoekenController` guard) |
| 7 | **Foto’s** | Geen upload/feature in code | — | — | — | — | **GAT / N.v.t.:** geen gebruikersfoto’s. Export heeft lege `fotos[]`. Bij latere upload: schijf + wissen bij opzegging verplicht |
| 8 | **Organisatieformulier** | `deelplekken` aanmelding: naam, type, plaats, `adres` = postcode-4+plaats (geen straat), beschrijving, `contact_email`, `privacy_at`, status | Deelplek tonen na goedkeuring | Toestemming | Pending tot goedkeuren/afwijzen; goedgekeurd zolang zichtbaar — **GAT: retentie afgewezen aanmeldingen niet geautomatiseerd** | Beheer (`/beheer`); publiek alleen goedgekeurde velden (geen contact-e-mail) | `contact_email` **encrypted**; rate-limit aanmelden; seed-deelplekken kunnen wél een openbaar bronadres hebben (**GAT/keuze**: seed vs. aanmelding) |
| 9 | **Sessies** | Tabel `sessions` (driver database): `user_id`, `ip_address`, `user_agent`, payload; cookies sessie + XSRF + remember | Login-state, CSRF | Gerechtvaardigd belang (beveiliging) / uitvoering | `SESSION_LIFETIME` (120 min); remember langer | Systeem; hosting-beheerder | `http_only`, `same_site=lax`, `secure` op staging/productie; wissen bij account-delete |
| 10 | **Logs** | `storage/logs/*` (niet in git) | Foutopsporing | Gerechtvaardigd belang | **GAT:** server-logrotatie (daily channel optioneel 14 d) — hosting | Beheerder/SSH | `MaskPersonalDataProcessor` + `SafeMail::safeSummary`; geen wachtwoorden loggen |
| 11 | **IP-adressen** | Rate-limit cache keys; `sessions.ip_address`; incidenteel in exceptions vóór masking | Rate limiting, sessie | Gerechtvaardigd belang (beveiliging) | Rate-limit TTL (minuten–uren); sessie lifetime | Systeem | Gemaskeerd in logs; geen aparte IP-database |
| 12 | **Rate-limit / cache** | Cache store (database `cache` tabel of array in tests); keys o.a. `login:…\|ip`, `register:ip`, `message:ip`, mail-throttle | Misbruik/spam tegengaan | Gerechtvaardigd belang | Korte TTL (`RATE_LIMIT_RETENTION_SECONDS` in `.env.example` als beleidstip; Laravel RateLimiter decay per hit) | Systeem | Keys bevatten e-mail/IP tijdelijk — **GAT: geen periodieke purge van verlopen cache-rijen naast Laravel lottery** |
| 13 | **Geocode-cache** | `geocode_cache`: query_key, label, afgeronde lat/lon, cached_at | Minder PDOK-calls | Gerechtvaardigd belang (performance) / toestemming zoekactie | `geocode_cache_retention_days` (90); cron `purgeGeocodeCache` | Systeem | Alleen genormaliseerde query + afgeronde coords |
| 14 | **Mail (SMTP)** | Config `mail.php`; transactionele mails (verify, reset, bericht, deelplek) via hosting-SMTP (typisch Hoasted) | Accountflows | Toestemming / uitvoering | Provider-retentie — **GAT: bevestigen bij hoster** | Ontvanger; SMTP-provider als verwerker | Geen berichtinhoud in “nieuw bericht”-mail; foutlogs gemaskeerd |

## Keuzes (motivatie)

1. **E-mail niet encrypted:** nodig als unique login-key en voor `password_reset_tokens` / notificaties. Versleuteling at rest zou deterministische encryptie of plaintext-index vereisen; hashing maakt login onmogelijk.
2. **Coördinaten 3 decimalen:** ~110 m — voldoende voor “in de buurt”, te grof voor huisadres.
3. **Beheer:** gedeeld `ADMIN_PASSWORD` in sessie (`admin_ok`); leeg = HTTP 503 op `/beheer`. Geen koppeling aan user-rollen.
4. **Backups:** command `backup:database-encrypted` — zie `docs/backups.md`. Server-cron/offsite = open.

## Rechten (implementatie)

| Recht | Pad / mechanisme |
| --- | --- |
| Inzage / export | `GET /account/export` → JSON (`AvgService::exportUserData`) |
| Wissen | `POST /account/verwijderen` (wachtwoord) → aanbod, berichten, gesprekken, blocks, reports, tokens, sessies, user; feedback blijft met `user_id` null |
| Rectificatie | `/account/profiel`, `/account/email`, `/account/wachtwoord` |
| Contact | `mailto:info@gratisplanten.nl` |

## Open gaten (samenvatting)

- Automatische purge inactieve accounts (beleid 12 maanden bestaat alleen als config/tekst).
- Retentiebeleid berichten / afgewezen deelplek-aanmeldingen.
- Gebruikersfoto’s: feature ontbreekt; bij toevoegen AVG-pad afmaken.
- Backup: cron, off-site opslag, aparte `BACKUP_ENCRYPTION_KEY`, bewaartermijn backups.
- HSTS preload-lijst: optioneel, niet in code.
- SMTP-/hosting-verwerkersovereenkomst en logretentie op server.
- Cache-tabel opruimen van verlopen rate-limit keys (naast TTL).
