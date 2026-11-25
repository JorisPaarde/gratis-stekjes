<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Nieuwsbrief (opt-out) via Brevo: alleen contacten/lijst, nooit mail of campagne.
    | sync_enabled (BREVO_SYNC_ENABLED) staat standaard UIT: zonder die schakelaar roept de site
    | Brevo nooit aan (staging raakt de echte lijst niet). Alleen productie zet hem aan.
    | Secrets nooit committen. Webhook-token beveiligt POST /webhooks/brevo/{token}.
    */
    'brevo' => [
        'sync_enabled' => filter_var(env('BREVO_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'api_key' => env('BREVO_API_KEY', ''),
        'newsletter_list_id' => (int) env('BREVO_NEWSLETTER_LIST_ID', 0),
        'webhook_token' => env('BREVO_WEBHOOK_TOKEN', ''),
    ],

];
