# Nieuwsbrief (opt-out) via Brevo

Sinds 0.82.0 staat de nieuwsbrief standaard **aan** voor bevestigde leden en is afmelden altijd mogelijk.
De site synchroniseert alleen **contacten naar Brevo-lijst 6 "Gratis planten nieuwsbrief"**. Ze verstuurt
nooit een mail of campagne (geen `/emailCampaigns`, geen `/smtp/email`). Verzenden doe je zelf in Brevo.

## Model

- `users.newsletter_opt_in` is de bron van waarheid. `newsletter_opt_in_at` = aanzetmoment,
  `newsletter_opt_out_at` = moment van afmelden (profiel of Brevo), `newsletter_listed_at` = laatst op de Brevo-lijst gezien.
- Registratie: vinkje standaard aan. Profiel: vinkje weghalen + Opslaan = afmelden (lokale vlag uit + contact van lijst 6).
- Alleen **bevestigde** (`email_verified_at`) en **actieve** leden komen op de lijst. Onbevestigde accounts nooit.
- Migratie `2026_11_25_140000_newsletter_opt_out_backfill` zet bevestigde, actieve leden zonder eerdere keuze op aan
  (idempotent; wie zelf uit stond, of al aan, wordt niet aangeraakt). `php artisan gp:nieuwsbrief-backfill --dry-run`
  toont alleen het aantal.
- Geen double opt-in en geen Brevo-DOI-mail.

## Schakelaar: staging raakt de echte lijst niet

`BREVO_SYNC_ENABLED` (GitHub-variabele per environment) staat standaard **uit**. Zonder `true` roept de site Brevo
nooit aan: profiel, registratie en webhook werken wel lokaal. Zet hem alleen op **production**.
Staging heeft wel de BREVO_*-secrets maar laat de variabele leeg.

## Sync (cron)

`CronController` draait `BrevoNewsletterService::sync()` max. 1× per 20 uur (en zolang er nog leden wachten, elke run
verder, max. 30 nieuwe contacten per run):

1. Lid met opt-in, niet op lijst → `GET /contacts/{email}`; staat het contact geblacklist of uitgeschreven van lijst 6,
   dan zet de site de vlag uit (Brevo wint). Anders `POST /contacts` met `listIds=[6]`, `updateEnabled=true`.
2. Lid stond op de lijst en is weg → afgemeld in Brevo → vlag uit.
3. Lid zonder opt-in (of onbevestigd/inactief) nog op de lijst → `POST /contacts/lists/6/contacts/remove`.
4. Contacten die geen lid zijn blijven ongemoeid.

Direct na e-mailbevestiging en na aanzetten in het profiel loopt dezelfde upsert als queue-job
(`SyncNewsletterContactJob`). Aanzetten in het profiel heft een eerdere blacklist van het contact op (expliciete keuze).

## Webhook (Brevo → site)

`POST /webhooks/brevo/{token}` (route `webhooks.brevo`, throttle 120/min). Beveiligd met `BREVO_WEBHOOK_TOKEN`
(`hash_equals`; 403 bij ontbrekend/onjuist token; token nooit in git). Events `unsubscribed`, `spam`,
`contactDeleted` (camelCase wordt genormaliseerd) zetten `newsletter_opt_in` uit en vullen `newsletter_opt_out_at`.
Antwoord bevat geen persoonsgegevens. Het webhook werkt ook als `BREVO_SYNC_ENABLED` uit staat.

### Webhook-URL omzetten bij de productie-release

De bestaande Brevo-webhook (Marketing → events unsubscribed/spam/contactDeleted) wijst nu naar staging. Pas bij de
prod-release, **nadat** deze versie live staat (anders 404):

1. Productie: secrets `BREVO_API_KEY`, `BREVO_NEWSLETTER_LIST_ID=6`, `BREVO_WEBHOOK_TOKEN` in GitHub Environment `production`
   (zelfde token als in de URL hieronder) en variabele `BREVO_SYNC_ENABLED=true`; daarna deploy (de hook behoudt/zet de waarden).
2. Brevo: webhook-URL wijzigen naar `https://gratisplanten.nl/webhooks/brevo/<BREVO_WEBHOOK_TOKEN>` (PUT /webhooks/{id}
   of Brevo-dashboard → Webhooks). Alleen de `url`; events ongewijzigd.
3. Controleer met een testmelding (Brevo "test webhook" of een gesimuleerde POST) dat het antwoord `{"ok":true,...}` is.
4. Eerste sync: draait vanzelf via `/cron`; controleer daarna het aantal contacten op lijst 6.

## Campagnes: afmeldlink verplicht

Elke campagne of template voor lijst 6 moet de Brevo-afmeldlink bevatten (`{{ unsubscribe }}` / Brevo-footer
"Uitschrijven"); Brevo weigert te versturen zonder. Controleer voor elke verzending in de campagne-preview dat de
link aanwezig is en dat de ontvangers uitsluitend lijst 6 zijn. De site maakt of verstuurt zelf nooit een campagne.
