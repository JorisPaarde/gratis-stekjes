<?php

declare(strict_types=1);

return [
    /** Minimale invultijd (seconden) voor auth-formulieren tegen bots. */
    'form_min_seconds' => (int) env('FORM_MIN_SECONDS', 3),

    /** Gebundelde MIT-lijst: resources/data/disposable/domains.txt */
    'disposable_domains_path' => resource_path('data/disposable/domains.txt'),

    /** Afronding coördinaten (privacy: geen exact adres). */
    'coord_decimals' => (int) env('COORD_DECIMALS', 3),

    /** PDOK Locatieserver free endpoint. */
    'pdok_url' => env('PDOK_URL', 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free'),

    /** Aanbod zichtbaar in zoeken/kaart (dagen). */
    'aanbod_ttl_days' => 60,

    /**
     * Aanbodfoto’s (AVG: wissen bij verwijderen aanbod/account).
     * Opslag buiten de webroot via disk `aanbod_fotos`; server-side herschrijven
     * stript EXIF/GPS. Alleen jpeg/png/webp na mime+decode-check.
     */
    'aanbod_fotos' => [
        'disk' => 'aanbod_fotos',
        'max_count' => 3,
        'max_bytes' => 8 * 1024 * 1024,
        'max_input_dimension' => 10000,
        'max_output_dimension' => 1600,
        'thumb_dimension' => 400,
        'upload_rate_per_hour' => 30,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    /** Herinneringsmail zoveel dagen vóór expires_at. */
    'aanbod_reminder_days' => 7,

    /** Geldigheid eenklik-verlengtoken (dagen). */
    'aanbod_renew_token_days' => 14,

    /** Geocode-cache opruimen na zoveel dagen. */
    'geocode_cache_retention_days' => (int) env('GEOCODE_CACHE_RETENTION_DAYS', 90),

    /** mail_log-regels opruimen na zoveel dagen (AVG-retentie; geen PII in de tabel). */
    'mail_log_retention_days' => (int) env('MAIL_LOG_RETENTION_DAYS', 90),

    /** Gedeeld secret voor /cron (header X-Cron-Token). */
    'cron_token' => env('CRON_TOKEN', ''),

    /**
     * Bearer-token voor POST /deploy/run (migratie + caches zonder SSH).
     * Leeg = endpoint weigert altijd. Nooit committen.
     */
    'deploy_token' => env('DEPLOY_TOKEN', ''),

    /**
     * Zet op true om /deploy/run buiten APP_ENV=staging toe te staan
     * (bijv. nood-debug). Staging schakelt het endpoint automatisch in.
     */
    'deploy_endpoint_enabled' => filter_var(env('DEPLOY_ENDPOINT_ENABLED', false), FILTER_VALIDATE_BOOL),

    /**
     * Eenmalige dataverhuis: POST /deploy/import(+ /init, /chunk).
     * Alleen true zetten tijdens migrate-stekjes-to-prod; daarna weer uit.
     * Vereist geldige DEPLOY_TOKEN. Default uit.
     */
    'deploy_import_enabled' => filter_var(env('DEPLOY_IMPORT_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Types voor kaartmarkers (interne sleutel partner_types blijft). */
    'partner_types' => [
        'partner' => 'Initiatief',
        'voedselbos' => 'Voedselbos',
        'plantenasiel' => 'Plantenasiel',
        'plantenbieb' => 'Plantenbieb',
        'ruilkast' => 'Ruilkast',
        'stekjesruilpunt' => 'Stekjesruilpunt',
        'zaadbibliotheek' => 'Zaadbibliotheek',
    ],

    /** Types voor landelijke deelplekken (/deelplekken). */
    'deelplek_types' => [
        'plantenasiel' => 'Plantenasiel',
        'plantenbieb' => 'Plantenbieb',
        'ruilkast' => 'Ruilkast',
        'stekjesruilpunt' => 'Stekjesruilpunt',
        'zaadbibliotheek' => 'Zaadbibliotheek',
        'voedselbos' => 'Voedselbos',
        'tuinvereniging' => 'Tuinvereniging',
    ],

    /** Types voor agenda-evenementen op /initiatieven. */
    'evenement_types' => [
        'stekjesmarkt' => 'Stekjesmarkt',
        'plantenruilbeurs' => 'Plantenruilbeurs',
        'ruildag' => 'Ruildag',
        'overig' => 'Overig',
    ],

    /** Nederlandse provincies (filter + beheer). */
    'provincies' => [
        'Drenthe',
        'Flevoland',
        'Friesland',
        'Gelderland',
        'Groningen',
        'Limburg',
        'Noord-Brabant',
        'Noord-Holland',
        'Overijssel',
        'Utrecht',
        'Zeeland',
        'Zuid-Holland',
    ],

    /** Gedeeld beheerwachtwoord (session admin_ok). Leeg = /beheer uit. */
    'admin_password' => env('ADMIN_PASSWORD', ''),

    /**
     * Beheer-mailbox voor meldingen (o.a. nieuwe deelplek-aanmelding).
     * Leeg = MAIL_FROM_ADDRESS. Geen derden; alleen interne admin-mail.
     */
    'admin_email' => env('ADMIN_EMAIL', env('MAIL_FROM_ADDRESS', '')),

    /**
     * Unieke account-e-mails (CSV) met Dashboard-menulink + /beheer zonder apart
     * wachtwoord (Gate see-admin-dashboard). Match alleen op users.email.
     * Lege default = val terug op admin_email; beide leeg = geen account-toegang.
     */
    'admin_dashboard_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_DASHBOARD_EMAILS', ''))
    ))),

    /** Dataminimalisatie / retentie (beleidstekst + toekomstige purge). */
    'inactive_account_months' => (int) env('INACTIVE_ACCOUNT_MONTHS', 12),

    /**
     * Sleutel voor versleutelde DB-backups (`php artisan backup:database-encrypted`).
     * Liever aparte BACKUP_ENCRYPTION_KEY; valt terug op APP_KEY. Nooit committen.
     */
    'backup_encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY', '')),

    /** Rolling bewaartermijn voor storage/backups/*.enc (dagen). */
    'backup_retain_days' => (int) env('BACKUP_RETAIN_DAYS', 30),

    /**
     * Blok met laatste goedgekeurde ervaringen op de homepage.
     * Default uit — aanzetten na content/moderatie.
     */
    'ervaringen_home_block_enabled' => filter_var(env('ERVARINGEN_HOME_BLOCK_ENABLED', false), FILTER_VALIDATE_BOOL),

    /**
     * Felicitatiemail bij eerste aanbod of eerste afgeronde overdracht.
     * HARDE EIS: default uit. Bij uit: niets queue'en/versturen, alleen loggen.
     */
    'milestone_mail_enabled' => filter_var(env('MILESTONE_MAIL_ENABLED', false), FILTER_VALIDATE_BOOL),
];
