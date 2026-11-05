<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversionEvent extends Model
{
    public $timestamps = false;

    public const TYPE_CTA_CLICK = 'cta_click';

    public const TYPE_REGISTER_VIEW = 'register_view';

    public const TYPE_FORM_START = 'form_start';

    public const TYPE_VALIDATION_ERROR = 'validation_error';

    public const TYPE_FORM_SUBMIT = 'form_submit';

    public const TYPE_ACCOUNT_CREATED = 'account_created';

    public const TYPE_EMAIL_VERIFIED = 'email_verified';

    public const TYPE_WELCOME_VIEW = 'welcome_view';

    public const TYPE_WELCOME_CTA_CLICK = 'welcome_cta_click';

    public const TYPE_FIRST_OFFER = 'first_offer';

    /**
     * Zoekactie op /aanbod of /api/zoeken (geen PII: geen ruwe q/plant-tekst).
     * Bewust niet in FUNNEL_TYPES — aparte zoekmeting naast de aanmeldtrechter.
     */
    public const TYPE_AANBOD_SEARCH = 'aanbod_search';

    /** Interne dagrij voor unieke-bezoekersschatting in conversion_daily. */
    public const TYPE_DAY_VISITORS = '_day_visitors';

    /**
     * Publieke trechterstappen op het dashboard.
     * welcome_view / welcome_cta_click / aanbod_search bewust niet hier:
     * hulpmetrics naast de trechter (email_verified → first_offer), zonder rates te breken.
     *
     * @var list<string>
     */
    public const FUNNEL_TYPES = [
        self::TYPE_CTA_CLICK,
        self::TYPE_REGISTER_VIEW,
        self::TYPE_FORM_START,
        self::TYPE_FORM_SUBMIT,
        self::TYPE_ACCOUNT_CREATED,
        self::TYPE_EMAIL_VERIFIED,
        self::TYPE_FIRST_OFFER,
    ];

    /**
     * Alleen form_start via publieke beacon; validation_error alleen server-side (RegisterRequest).
     *
     * @var list<string>
     */
    public const BEACON_TYPES = [
        self::TYPE_FORM_START,
    ];

    /** @var list<string> Toegestane paden voor /meet/event */
    public const BEACON_PATHS = [
        '/account/registreren',
    ];

    protected $fillable = [
        'occurred_at',
        'event_type',
        'path',
        'flow_id',
        'visitor_day_hash',
        'referrer_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'device_type',
        'field_name',
        'error_code',
        'week_key',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
