<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConversionDaily;
use App\Models\ConversionEvent;
use App\Models\User;
use App\Support\AdminDashboard;
use App\Support\BotUserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Privacyvriendelijke conversiemeting: geen IP/UA opslaan, geen trackingcookies van derden.
 */
final class ConversionTracker
{
    public const DEVICE_MOBILE = 'mobile';

    public const DEVICE_TABLET = 'tablet';

    public const DEVICE_DESKTOP = 'desktop';

    /** @var array<string, string> Laravel-regel → vaste foutcode zonder invoer. */
    private const ERROR_CODE_MAP = [
        'required' => 'verplicht',
        'accepted' => 'verplicht',
        'email' => 'ongeldig',
        'min' => 'te_kort',
        'max' => 'te_lang',
        'confirmed' => 'komt_niet_overeen',
        'unique' => 'bestaat_al',
        'not_regex' => 'ongeldig',
        'boolean' => 'ongeldig',
        'integer' => 'ongeldig',
        'string' => 'ongeldig',
    ];

    /** @var array<string, string> */
    private const FIELD_ALIASES = [
        'password' => 'wachtwoord',
        'password_confirmation' => 'wachtwoord_bevestiging',
        'voornaam' => 'voornaam',
        'email' => 'email',
        'gratis_akkoord' => 'gratis_akkoord',
        'privacy' => 'privacy',
        'form_started' => 'form_started',
        'acquisition_found_via' => 'gevonden_via',
        'acquisition_found_via_other' => 'gevonden_via_anders',
    ];

    public function isBot(Request $request): bool
    {
        return BotUserAgent::isBot($request);
    }

    /**
     * Geen meting voor bots, beheerpaden, stats-lezer, beheerders of admin-sessie.
     */
    public function shouldSkipMeasurement(Request $request): bool
    {
        if ($this->isBot($request)) {
            return true;
        }

        $path = $this->normalizePath($request->getPathInfo()) ?? '';
        if ($path === '/beheer' || str_starts_with($path, '/beheer/')) {
            return true;
        }

        $user = $request->user();
        if ($user instanceof User) {
            if (method_exists($user, 'isStatsViewer') && $user->isStatsViewer()) {
                return true;
            }
            if ((bool) ($user->is_admin ?? false) || AdminDashboard::userMayAccess($user)) {
                return true;
            }
        }

        try {
            if (Gate::allows('see-admin-dashboard')) {
                return true;
            }
        } catch (\Throwable) {
            // Gate zonder auth-context.
        }

        if ($request->hasSession() && $request->session()->get('admin_ok')) {
            return true;
        }

        return false;
    }

    public function flowTtlDays(): int
    {
        return max(1, (int) config('conversion.flow_ttl_days', 7));
    }

    /**
     * Oorspronkelijke flow-vervaldatum (niet verlengen bij latere hits/registratie).
     *
     * @param  array{created_at?: int, expires_at?: int}  $ctx
     */
    public function flowExpiresAt(array $ctx, ?Carbon $now = null): Carbon
    {
        $now ??= now();
        if (isset($ctx['expires_at']) && (int) $ctx['expires_at'] > 0) {
            return Carbon::createFromTimestamp((int) $ctx['expires_at']);
        }
        $created = (int) ($ctx['created_at'] ?? $now->getTimestamp());

        return Carbon::createFromTimestamp($created)->addDays($this->flowTtlDays());
    }

    public function deviceType(Request $request): string
    {
        $ua = strtolower(trim((string) $request->userAgent()));
        if ($ua === '') {
            return self::DEVICE_DESKTOP;
        }
        if (preg_match('/ipad|tablet|kindle|playbook|silk|(android(?!.*mobile))/i', $ua)) {
            return self::DEVICE_TABLET;
        }
        if (preg_match('/mobi|iphone|ipod|android.*mobile|windows phone|opera mini/i', $ua)) {
            return self::DEVICE_MOBILE;
        }

        return self::DEVICE_DESKTOP;
    }

    public function normalizePath(?string $pathInfo): ?string
    {
        if ($pathInfo === null || $pathInfo === '') {
            return '/';
        }

        $path = '/'.ltrim($pathInfo, '/');
        $path = rawurldecode($path);
        $path = strtok($path, '?#') ?: '/';
        $path = (string) preg_replace('#/+#', '/', $path);

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if ($path === '') {
            $path = '/';
        }

        if (strlen($path) > 160) {
            return '/_other';
        }

        return $path;
    }

    public function referrerHost(Request $request): ?string
    {
        $ref = trim((string) $request->headers->get('Referer', ''));
        if ($ref === '') {
            return null;
        }
        $host = parse_url($ref, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }
        $host = strtolower($host);
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '' && ($host === $appHost || str_ends_with($host, '.'.$appHost))) {
            return null;
        }

        return Str::limit($host, 120, '');
    }

    public function weekKey(?Carbon $at = null): string
    {
        $at ??= now();

        return $at->copy()->timezone('Europe/Amsterdam')->format('o-\WW');
    }

    /**
     * Schatting unieke bezoeker/dag: HMAC(secret+datum+UA+IP-prefix). Alleen hash opslaan.
     */
    public function visitorDayHash(Request $request, ?Carbon $at = null): string
    {
        $at ??= now();
        $date = $at->copy()->timezone('Europe/Amsterdam')->toDateString();
        $secret = (string) config('conversion.visitor_hash_secret');
        if ($secret === '') {
            $secret = (string) config('app.key');
        }
        $ua = substr(hash('sha256', (string) $request->userAgent()), 0, 16);
        $ip = (string) $request->ip();
        $ipPart = $this->ipPrefix($ip);

        return hash_hmac('sha256', $date.'|'.$ua.'|'.$ipPart, $secret);
    }

    private function ipPrefix(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return ($parts[0] ?? '0').'.'.($parts[1] ?? '0').'.'.($parts[2] ?? '0');
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 4));
        }

        return 'unknown';
    }

    /**
     * @return array{
     *   flow_id: string,
     *   landing_path: ?string,
     *   referrer_host: ?string,
     *   utm_source: ?string,
     *   utm_medium: ?string,
     *   utm_campaign: ?string,
     *   utm_content: ?string,
     *   created_at: int,
     *   expires_at: int
     * }
     */
    public function context(Request $request): array
    {
        $cookieName = (string) config('conversion.flow_cookie', 'gp_flow');
        $raw = (string) $request->cookie($cookieName, '');
        $data = $this->decodeFlowCookie($raw);

        $now = now()->getTimestamp();
        $expired = $data !== null && $this->flowExpiresAt($data)->getTimestamp() <= $now;

        if ($data === null || $expired || ($data['flow_id'] ?? '') === '') {
            $ttlDays = $this->flowTtlDays();
            $data = [
                'flow_id' => Str::lower(Str::random(32)),
                'landing_path' => null,
                'referrer_host' => null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'utm_content' => null,
                'created_at' => $now,
                'expires_at' => $now + ($ttlDays * 86400),
            ];
        } else {
            // Behoud oorspronkelijke vervaldatum; nooit verlengen.
            $data['expires_at'] = $this->flowExpiresAt($data)->getTimestamp();
        }

        // First-touch: alleen vullen als nog leeg.
        if (($data['landing_path'] ?? null) === null) {
            $path = $this->normalizePath($request->getPathInfo());
            if ($path !== null && ! str_starts_with($path, '/ga/') && ! str_starts_with($path, '/meet/')) {
                $data['landing_path'] = $path;
            }
        }
        if (($data['referrer_host'] ?? null) === null) {
            $data['referrer_host'] = $this->referrerHost($request);
        }
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'] as $key) {
            if (($data[$key] ?? null) === null) {
                $val = trim((string) $request->query($key, ''));
                if ($val !== '') {
                    $data[$key] = Str::limit($val, $key === 'utm_campaign' || $key === 'utm_content' ? 120 : 80, '');
                }
            }
        }

        // Sessie-spiegel (voor registratie) zonder extra cookie.
        if ($request->hasSession()) {
            $session = $request->session();
            if (! $session->has('conversion_flow_id')) {
                $session->put('conversion_flow_id', $data['flow_id']);
            }
            if (! $session->has('acquisition')) {
                $session->put('acquisition', [
                    'landing_path' => $data['landing_path'],
                    'referrer_host' => $data['referrer_host'],
                    'utm_source' => $data['utm_source'],
                    'utm_medium' => $data['utm_medium'],
                    'utm_campaign' => $data['utm_campaign'],
                    'utm_content' => $data['utm_content'],
                ]);
            }
        }

        $request->attributes->set('conversion_context', $data);

        return $data;
    }

    public function queueFlowCookie(Request $request): void
    {
        if ($this->isBot($request)) {
            return;
        }

        $data = $request->attributes->get('conversion_context') ?? $this->context($request);
        $cookieName = (string) config('conversion.flow_cookie', 'gp_flow');
        $expiresAt = $this->flowExpiresAt(is_array($data) ? $data : []);
        $remainingMinutes = max(1, (int) ceil(($expiresAt->getTimestamp() - now()->getTimestamp()) / 60));
        $encoded = $this->encodeFlowCookie($data);

        // HttpOnly + SameSite=Lax; Secure automatisch op HTTPS ($request->isSecure()).
        Cookie::queue(cookie(
            $cookieName,
            $encoded,
            $remainingMinutes,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'Lax'
        ));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function record(string $eventType, Request $request, array $extra = []): void
    {
        try {
            $this->recordUnsafe($eventType, $request, $extra);
        } catch (\Throwable $e) {
            Log::warning('conversion: meting mislukt', [
                'event' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Privacyvriendelijke zoekmeting op het aanbod.
     *
     * Opslag (geen migratie):
     * - field_name = canonieke NL-plantennaam bij Plantensoorten::resolveExact, anders null
     *   (nooit ruwe plant-/locatietekst)
     * - error_code = "{modus}.{type}.{bucket}.{geocode}"
     *   modus: locatie|plant|beide
     *   type: uitdelen|ophalen|alle
     *   bucket: 0|1-5|6-20|21+
     *   geocode: ok|miss|na
     *
     * @param  'uitdelen'|'ophalen'|null  $typeFilter
     */
    public function recordAanbodSearch(
        Request $request,
        string $q,
        string $plant,
        ?string $typeFilter,
        int $resultCount,
        bool $geocodeMiss = false,
    ): void {
        $q = trim($q);
        $plant = trim($plant);
        if ($q === '' && $plant === '') {
            return;
        }

        $modus = match (true) {
            $q !== '' && $plant !== '' => 'beide',
            $plant !== '' => 'plant',
            default => 'locatie',
        };

        $type = in_array($typeFilter, ['uitdelen', 'ophalen'], true) ? $typeFilter : 'alle';
        $bucket = self::aanbodResultBucket($resultCount);
        $geocode = $q === '' ? 'na' : ($geocodeMiss ? 'miss' : 'ok');

        $catalogNaam = null;
        if ($plant !== '') {
            try {
                $resolved = app(Plantensoorten::class)->resolveExact($plant);
                if (is_array($resolved) && isset($resolved['naam']) && is_string($resolved['naam']) && $resolved['naam'] !== '') {
                    $catalogNaam = $resolved['naam'];
                }
            } catch (\Throwable) {
                $catalogNaam = null;
            }
        }

        $this->record(ConversionEvent::TYPE_AANBOD_SEARCH, $request, [
            'field_name' => $catalogNaam,
            'error_code' => $modus.'.'.$type.'.'.$bucket.'.'.$geocode,
        ]);
    }

    public static function aanbodResultBucket(int $resultCount): string
    {
        $n = max(0, $resultCount);
        if ($n === 0) {
            return '0';
        }
        if ($n <= 5) {
            return '1-5';
        }
        if ($n <= 20) {
            return '6-20';
        }

        return '21+';
    }

    /**
     * @return array{modus: string, type: string, bucket: string, geocode: string}|null
     */
    public static function parseAanbodSearchCode(?string $errorCode): ?array
    {
        if ($errorCode === null || $errorCode === '') {
            return null;
        }
        $parts = explode('.', $errorCode);
        if (count($parts) !== 4) {
            return null;
        }
        [$modus, $type, $bucket, $geocode] = $parts;
        if (! in_array($modus, ['locatie', 'plant', 'beide'], true)) {
            return null;
        }
        if (! in_array($type, ['uitdelen', 'ophalen', 'alle'], true)) {
            return null;
        }
        if (! in_array($bucket, ['0', '1-5', '6-20', '21+'], true)) {
            return null;
        }
        if (! in_array($geocode, ['ok', 'miss', 'na'], true)) {
            return null;
        }

        return [
            'modus' => $modus,
            'type' => $type,
            'bucket' => $bucket,
            'geocode' => $geocode,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function recordUnsafe(string $eventType, Request $request, array $extra = []): void
    {
        if (! Schema::hasTable('conversion_events')) {
            return;
        }

        if ($this->shouldSkipMeasurement($request)) {
            return;
        }

        $ctx = $request->attributes->get('conversion_context');
        if (! is_array($ctx)) {
            $ctx = $this->context($request);
        }

        $at = now();
        $flowId = isset($extra['flow_id']) && is_string($extra['flow_id']) && $extra['flow_id'] !== ''
            ? $extra['flow_id']
            : (string) ($ctx['flow_id'] ?? '');

        // Geen flow_id meer koppelen na oorspronkelijke 7-dagen-vervaldatum.
        if ($flowId !== '' && ! isset($extra['flow_id']) && $this->flowExpiresAt($ctx, $at)->isPast()) {
            $flowId = '';
        }

        $path = isset($extra['path'])
            ? $this->normalizePath((string) $extra['path'])
            : $this->normalizePath($request->getPathInfo());

        ConversionEvent::query()->create([
            'occurred_at' => $at,
            'event_type' => Str::limit($eventType, 40, ''),
            'path' => $path,
            'flow_id' => $flowId !== '' ? Str::limit($flowId, 64, '') : null,
            'visitor_day_hash' => $this->visitorDayHash($request, $at),
            'referrer_host' => $extra['referrer_host'] ?? $ctx['referrer_host'] ?? $this->referrerHost($request),
            'utm_source' => $this->clip($extra['utm_source'] ?? $ctx['utm_source'] ?? $request->query('utm_source'), 80),
            'utm_medium' => $this->clip($extra['utm_medium'] ?? $ctx['utm_medium'] ?? $request->query('utm_medium'), 80),
            'utm_campaign' => $this->clip($extra['utm_campaign'] ?? $ctx['utm_campaign'] ?? $request->query('utm_campaign'), 120),
            'utm_content' => $this->clip($extra['utm_content'] ?? $ctx['utm_content'] ?? $request->query('utm_content'), 120),
            'device_type' => $extra['device_type'] ?? $this->deviceType($request),
            'field_name' => isset($extra['field_name']) ? $this->clip($extra['field_name'], 60) : null,
            'error_code' => isset($extra['error_code']) ? $this->clip($extra['error_code'], 80) : null,
            'week_key' => $this->weekKey($at),
        ]);
    }

    private function clip(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        return Str::limit($s, $max, '');
    }

    /**
     * @param  array<string, list<string>>|\Illuminate\Support\MessageBag  $errors
     */
    public function recordValidationErrors(Request $request, $errors): void
    {
        $bag = $errors instanceof \Illuminate\Support\MessageBag
            ? $errors->toArray()
            : (array) $errors;

        foreach (array_keys($bag) as $field) {
            $field = (string) $field;
            // Nooit wachtwoord/e-mail-inhoud; alleen veldnaam + code.
            if (in_array($field, ['website'], true)) {
                continue;
            }
            $code = $this->errorCodeForField($request, $field);
            $this->record(ConversionEvent::TYPE_VALIDATION_ERROR, $request, [
                'field_name' => self::FIELD_ALIASES[$field] ?? Str::limit($field, 60, ''),
                'error_code' => $code,
                'path' => '/account/registreren',
            ]);
        }
    }

    private function errorCodeForField(Request $request, string $field): string
    {
        $alias = self::FIELD_ALIASES[$field] ?? $field;
        $failed = [];
        try {
            /** @var \Illuminate\Validation\Validator|null $validator */
            $validator = $request->attributes->get('conversion_failed_validator');
            if ($validator instanceof \Illuminate\Validation\Validator) {
                $failed = $validator->failed()[$field] ?? [];
            }
        } catch (\Throwable) {
            $failed = [];
        }

        $rule = strtolower((string) array_key_first($failed));
        $mapped = self::ERROR_CODE_MAP[$rule] ?? ($rule !== '' ? $rule : 'ongeldig');

        return Str::limit($alias.'.'.$mapped, 80, '');
    }

    /**
     * @return array{flow_id: string, landing_path: ?string, referrer_host: ?string, utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_content: ?string, created_at: int}|null
     */
    private function decodeFlowCookie(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        try {
            // Laravel EncryptCookies ontsleutelt de cookie al; hier alleen JSON.
            $data = json_decode($raw, true);
            if (! is_array($data) || ! isset($data['flow_id']) || ! is_string($data['flow_id'])) {
                return null;
            }

            $created = (int) ($data['created_at'] ?? now()->getTimestamp());
            $expires = isset($data['expires_at'])
                ? (int) $data['expires_at']
                : $created + ($this->flowTtlDays() * 86400);

            return [
                'flow_id' => Str::limit($data['flow_id'], 64, ''),
                'landing_path' => isset($data['landing_path']) ? $this->clip($data['landing_path'], 180) : null,
                'referrer_host' => isset($data['referrer_host']) ? $this->clip($data['referrer_host'], 120) : null,
                'utm_source' => isset($data['utm_source']) ? $this->clip($data['utm_source'], 80) : null,
                'utm_medium' => isset($data['utm_medium']) ? $this->clip($data['utm_medium'], 80) : null,
                'utm_campaign' => isset($data['utm_campaign']) ? $this->clip($data['utm_campaign'], 120) : null,
                'utm_content' => isset($data['utm_content']) ? $this->clip($data['utm_content'], 120) : null,
                'created_at' => $created,
                'expires_at' => $expires,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{flow_id: string, landing_path: ?string, referrer_host: ?string, utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_content: ?string, created_at: int, expires_at: int}  $data
     */
    private function encodeFlowCookie(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function acquisitionFromRequest(Request $request): array
    {
        $ctx = $request->attributes->get('conversion_context');
        if (! is_array($ctx)) {
            $ctx = $this->context($request);
        }

        $fromSession = $request->hasSession()
            ? (array) $request->session()->get('acquisition', [])
            : [];

        // Oorspronkelijke flow-vervaldatum behouden — niet opnieuw +7 dagen bij registratie.
        $expiresAt = $this->flowExpiresAt($ctx);

        return [
            'acquisition_landing_path' => $fromSession['landing_path'] ?? $ctx['landing_path'] ?? null,
            'acquisition_referrer_host' => $fromSession['referrer_host'] ?? $ctx['referrer_host'] ?? null,
            'acquisition_utm_source' => $fromSession['utm_source'] ?? $ctx['utm_source'] ?? null,
            'acquisition_utm_medium' => $fromSession['utm_medium'] ?? $ctx['utm_medium'] ?? null,
            'acquisition_utm_campaign' => $fromSession['utm_campaign'] ?? $ctx['utm_campaign'] ?? null,
            'acquisition_utm_content' => $fromSession['utm_content'] ?? $ctx['utm_content'] ?? null,
            'conversion_flow_id' => (string) ($ctx['flow_id'] ?? ''),
            'conversion_flow_expires_at' => $expiresAt,
        ];
    }

    public function attachFlowToUser(User $user, Request $request): void
    {
        if (! Schema::hasColumn('users', 'conversion_flow_id')) {
            return;
        }
        $acq = $this->acquisitionFromRequest($request);
        $user->forceFill([
            'conversion_flow_id' => $acq['conversion_flow_id'] !== '' ? $acq['conversion_flow_id'] : null,
            'conversion_flow_expires_at' => $acq['conversion_flow_expires_at'],
            'acquisition_landing_path' => $acq['acquisition_landing_path'],
            'acquisition_referrer_host' => $acq['acquisition_referrer_host'],
            'acquisition_utm_source' => $acq['acquisition_utm_source'],
            'acquisition_utm_medium' => $acq['acquisition_utm_medium'],
            'acquisition_utm_campaign' => $acq['acquisition_utm_campaign'],
            'acquisition_utm_content' => $acq['acquisition_utm_content'],
        ])->save();
    }

    public function recordForUser(string $eventType, User $user, Request $request, array $extra = []): void
    {
        $flowId = null;
        if (Schema::hasColumn('users', 'conversion_flow_id')) {
            $expires = $user->conversion_flow_expires_at;
            if (is_string($user->conversion_flow_id) && $user->conversion_flow_id !== '') {
                if ($expires === null || $expires->isFuture()) {
                    $flowId = $user->conversion_flow_id;
                }
            }
        }

        $extra['flow_id'] = $flowId ?? ($extra['flow_id'] ?? null);
        // UTM van account (first-touch) heeft voorrang op huidige request.
        foreach (['utm_source' => 'acquisition_utm_source', 'utm_medium' => 'acquisition_utm_medium', 'utm_campaign' => 'acquisition_utm_campaign', 'utm_content' => 'acquisition_utm_content'] as $utm => $col) {
            if (! isset($extra[$utm]) && Schema::hasColumn('users', $col) && filled($user->{$col})) {
                $extra[$utm] = $user->{$col};
            }
        }
        if (! isset($extra['referrer_host']) && Schema::hasColumn('users', 'acquisition_referrer_host') && filled($user->acquisition_referrer_host)) {
            $extra['referrer_host'] = $user->acquisition_referrer_host;
        }

        $this->record($eventType, $request, $extra);
    }

    /**
     * Aggregeer events naar conversion_daily, null flow_id na 7 dagen, wis ruwe events na retentie.
     *
     * @return array{aggregated_days: int, deleted_events: int, cleared_flows: int, nulled_event_flows: int}
     */
    public function aggregateAndPurge(?Carbon $now = null): array
    {
        $now ??= now();
        $aggregatedDays = 0;
        $deleted = 0;
        $clearedFlows = 0;
        $nulledEventFlows = 0;

        if (Schema::hasTable('conversion_events') && Schema::hasTable('conversion_daily')) {
            // Amsterdam-dagen (zelfde grenzen als aggregateDay), niet DATE(occurred_at) in UTC.
            foreach ($this->amsterdamDaysWithRawEvents() as $dayStr) {
                $this->aggregateDay($dayStr);
                $aggregatedDays++;
            }

            // Flow-id op ruwe events uiterlijk 7 dagen na ontstaan wissen (events blijven tot raw-retentie).
            $flowCutoff = $now->copy()->subDays($this->flowTtlDays());
            $nulledEventFlows = ConversionEvent::query()
                ->whereNotNull('flow_id')
                ->where('occurred_at', '<', $flowCutoff)
                ->update(['flow_id' => null]);

            $cutoff = $now->copy()->subDays(max(1, (int) config('conversion.raw_retention_days', 30)));
            $deleted = ConversionEvent::query()
                ->where('occurred_at', '<', $cutoff)
                ->delete();
        }

        if (Schema::hasColumn('users', 'conversion_flow_id')) {
            $clearedFlows = User::query()
                ->whereNotNull('conversion_flow_id')
                ->where(function ($q) use ($now): void {
                    $q->where('conversion_flow_expires_at', '<', $now)
                        ->orWhere(function ($q2) use ($now): void {
                            $q2->whereNull('conversion_flow_expires_at')
                                ->where('created_at', '<', $now->copy()->subDays($this->flowTtlDays()));
                        });
                })
                ->update([
                    'conversion_flow_id' => null,
                    'conversion_flow_expires_at' => null,
                ]);
        }

        return [
            'aggregated_days' => $aggregatedDays,
            'deleted_events' => $deleted,
            'cleared_flows' => $clearedFlows,
            'nulled_event_flows' => $nulledEventFlows,
        ];
    }

    /**
     * Unieke kalenderdagen (Europe/Amsterdam) waarvoor nog raw events bestaan.
     * Gebruikt dezelfde daggrenzen als aggregateDay — niet DATE(occurred_at) in DB-UTC.
     *
     * @return list<string> Y-m-d
     */
    private function amsterdamDaysWithRawEvents(): array
    {
        $bounds = ConversionEvent::query()
            ->selectRaw('MIN(occurred_at) as min_at, MAX(occurred_at) as max_at')
            ->first();

        if ($bounds === null || $bounds->min_at === null || $bounds->max_at === null) {
            return [];
        }

        $cursor = Carbon::parse($bounds->min_at)->timezone('Europe/Amsterdam')->startOfDay();
        $last = Carbon::parse($bounds->max_at)->timezone('Europe/Amsterdam')->startOfDay();
        $days = [];

        while ($cursor->lte($last)) {
            $dayStr = $cursor->toDateString();
            $startUtc = $cursor->copy()->startOfDay()->utc();
            $endUtc = $cursor->copy()->endOfDay()->utc();
            if (ConversionEvent::query()->whereBetween('occurred_at', [$startUtc, $endUtc])->exists()) {
                $days[] = $dayStr;
            }
            $cursor->addDay();
        }

        return $days;
    }

    private function aggregateDay(string $day): void
    {
        $start = Carbon::parse($day, 'Europe/Amsterdam')->startOfDay();
        $end = $start->copy()->endOfDay();
        $weekKey = $this->weekKey($start);

        $rows = ConversionEvent::query()
            ->whereBetween('occurred_at', [$start->utc(), $end->utc()])
            ->get();

        // Verwijder bestaande daily-rijen voor deze dag en herschrijf (idempotent).
        ConversionDaily::query()->whereDate('date', $day)->delete();

        $groups = [];
        $uniques = [];
        foreach ($rows as $row) {
            $key = implode("\0", [
                (string) $row->event_type,
                (string) ($row->device_type ?? ''),
                (string) ($row->utm_source ?? ''),
                (string) ($row->utm_medium ?? ''),
                (string) ($row->utm_campaign ?? ''),
                (string) ($row->utm_content ?? ''),
                (string) ($row->field_name ?? ''),
                (string) ($row->error_code ?? ''),
            ]);
            $groups[$key] = ($groups[$key] ?? 0) + 1;
            if (is_string($row->visitor_day_hash) && $row->visitor_day_hash !== '') {
                $uniques[$key][$row->visitor_day_hash] = true;
            }
        }

        $insert = [];
        foreach ($groups as $key => $count) {
            [$eventType, $device, $src, $med, $camp, $content, $field, $code] = explode("\0", $key);
            $insert[] = [
                'date' => $day,
                'week_key' => $weekKey,
                'event_type' => $eventType,
                'device_type' => $device !== '' ? $device : null,
                'utm_source' => $src !== '' ? $src : null,
                'utm_medium' => $med !== '' ? $med : null,
                'utm_campaign' => $camp !== '' ? $camp : null,
                'utm_content' => $content !== '' ? $content : null,
                'field_name' => $field !== '' ? $field : null,
                'error_code' => $code !== '' ? $code : null,
                'count' => $count,
                'unique_visitors' => isset($uniques[$key]) ? count($uniques[$key]) : 0,
            ];
        }

        // Dagtotalen voor unieke-bezoekersschatting (bovengrens bij weekaggregatie).
        $dayHashes = [];
        foreach ($rows as $row) {
            if (is_string($row->visitor_day_hash) && $row->visitor_day_hash !== '') {
                $dayHashes[$row->visitor_day_hash] = true;
            }
        }
        $insert[] = [
            'date' => $day,
            'week_key' => $weekKey,
            'event_type' => ConversionEvent::TYPE_DAY_VISITORS,
            'device_type' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_content' => null,
            'field_name' => null,
            'error_code' => null,
            'count' => $rows->count(),
            'unique_visitors' => count($dayHashes),
        ];

        foreach (array_chunk($insert, 100) as $chunk) {
            ConversionDaily::query()->insert($chunk);
        }
    }

    /**
     * Trechtercijfers per week uit conversion_daily (fallback: ruwe events).
     *
     * @return list<array{
     *   week_key: string,
     *   label: string,
     *   unique_visitors: int,
     *   steps: array<string, int>,
     *   rates: array<string, float|null>,
     *   too_few: bool
     * }>
     */
    public function funnelByWeek(int $weeks = 8): array
    {
        $out = [];
        $tz = 'Europe/Amsterdam';
        $cursor = now($tz)->startOfWeek(Carbon::MONDAY);

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $cursor->copy()->subWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            $weekKey = $this->weekKey($weekStart);
            $steps = [];
            foreach (ConversionEvent::FUNNEL_TYPES as $type) {
                $steps[$type] = $this->countInRange($type, $weekStart, $weekEnd);
            }
            $unique = $this->uniqueVisitorsInRange($weekStart, $weekEnd);
            $formStarts = $steps[ConversionEvent::TYPE_FORM_START] ?? 0;
            $minStarts = max(1, (int) config('conversion.min_form_starts_for_rates', 30));
            $tooFew = $formStarts < $minStarts;

            $order = array_merge(['unique_visitors'], ConversionEvent::FUNNEL_TYPES);
            $values = ['unique_visitors' => $unique] + $steps;
            $rates = [];
            $prev = null;
            foreach ($order as $step) {
                $val = (int) ($values[$step] ?? 0);
                if ($tooFew || $prev === null || $prev === 0) {
                    $rates[$step] = null;
                } else {
                    $rates[$step] = round(($val / $prev) * 100, 1);
                }
                $prev = $val;
            }

            $out[] = [
                'week_key' => $weekKey,
                'label' => $weekStart->format('d-m').' – '.$weekEnd->format('d-m'),
                'unique_visitors' => $unique,
                'steps' => $steps,
                'rates' => $rates,
                'too_few' => $tooFew,
            ];
        }

        return $out;
    }

    private function countInRange(string $eventType, Carbon $from, Carbon $to): int
    {
        if (Schema::hasTable('conversion_daily')) {
            $sum = (int) ConversionDaily::query()
                ->where('event_type', $eventType)
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->sum('count');
            if ($sum > 0) {
                return $sum;
            }
        }

        if (! Schema::hasTable('conversion_events')) {
            return 0;
        }

        return (int) ConversionEvent::query()
            ->where('event_type', $eventType)
            ->whereBetween('occurred_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->count();
    }

    /**
     * Unieke bezoekers in een periode.
     *
     * Met volledige raw-dekking: distinct visitor_day_hash.
     * Na (gedeeltelijke) raw-retentie: som van `_day_visitors.unique_visitors` per dag
     * als bovengrens (kan cross-day overlap dubbel tellen).
     */
    private function uniqueVisitorsInRange(Carbon $from, Carbon $to): int
    {
        $partialPurge = false;
        if (Schema::hasTable('conversion_daily') && Schema::hasTable('conversion_events')) {
            $dailyDays = ConversionDaily::query()
                ->where('event_type', ConversionEvent::TYPE_DAY_VISITORS)
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->pluck('date');

            foreach ($dailyDays as $d) {
                $dayStr = $d instanceof Carbon ? $d->toDateString() : (string) $d;
                $dayStart = Carbon::parse($dayStr, 'Europe/Amsterdam')->startOfDay()->utc();
                $dayEnd = Carbon::parse($dayStr, 'Europe/Amsterdam')->endOfDay()->utc();
                $hasRaw = ConversionEvent::query()
                    ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                    ->exists();
                if (! $hasRaw) {
                    $partialPurge = true;
                    break;
                }
            }
        }

        if (! $partialPurge && Schema::hasTable('conversion_events')) {
            $raw = (int) ConversionEvent::query()
                ->whereBetween('occurred_at', [$from->copy()->utc(), $to->copy()->utc()])
                ->whereNotNull('visitor_day_hash')
                ->distinct()
                ->count('visitor_day_hash');
            if ($raw > 0 || ! Schema::hasTable('conversion_daily')) {
                return $raw;
            }
        }

        if (Schema::hasTable('conversion_daily')) {
            // Bovengrens na raw-retentie: som per-dag unique (geen de-duplicatie over dagen).
            return (int) ConversionDaily::query()
                ->where('event_type', ConversionEvent::TYPE_DAY_VISITORS)
                ->whereDate('date', '>=', $from->toDateString())
                ->whereDate('date', '<=', $to->toDateString())
                ->sum('unique_visitors');
        }

        return 0;
    }

    /**
     * Zoekmeting-samenvatting voor één kalenderweek (Amsterdam, ma–zo).
     *
     * @return array{
     *   week_key: string,
     *   label: string,
     *   total: int,
     *   empty_count: int,
     *   empty_pct: float|null,
     *   modus: array{locatie: int, plant: int, beide: int}
     * }
     */
    public function aanbodSearchWeekSummary(int $weekOffset = 0): array
    {
        $tz = 'Europe/Amsterdam';
        $weekStart = now($tz)->startOfWeek(Carbon::MONDAY)->subWeeks(max(0, $weekOffset));
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $rows = $this->aanbodSearchDimensionRows($weekStart, $weekEnd);

        $total = 0;
        $empty = 0;
        $modus = ['locatie' => 0, 'plant' => 0, 'beide' => 0];
        foreach ($rows as $row) {
            $count = (int) $row['count'];
            $total += $count;
            $parsed = self::parseAanbodSearchCode($row['error_code']);
            if ($parsed === null) {
                continue;
            }
            if ($parsed['bucket'] === '0') {
                $empty += $count;
            }
            if (isset($modus[$parsed['modus']])) {
                $modus[$parsed['modus']] += $count;
            }
        }

        return [
            'week_key' => $this->weekKey($weekStart),
            'label' => $weekStart->format('d-m').' – '.$weekEnd->format('d-m'),
            'total' => $total,
            'empty_count' => $empty,
            'empty_pct' => $total > 0 ? round(($empty / $total) * 100, 1) : null,
            'modus' => $modus,
        ];
    }

    /**
     * Top gezochte catalogusplanten in een kalenderweek (alleen resolveExact-matches).
     *
     * @return list<array{naam: string, count: int, empty_count: int, empty_pct: float|null}>
     */
    public function topAanbodSearchPlants(int $limit = 10, int $weekOffset = 0): array
    {
        $tz = 'Europe/Amsterdam';
        $weekStart = now($tz)->startOfWeek(Carbon::MONDAY)->subWeeks(max(0, $weekOffset));
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $rows = $this->aanbodSearchDimensionRows($weekStart, $weekEnd);

        /** @var array<string, array{count: int, empty_count: int}> $byPlant */
        $byPlant = [];
        foreach ($rows as $row) {
            $naam = trim((string) ($row['field_name'] ?? ''));
            if ($naam === '') {
                continue;
            }
            $count = (int) $row['count'];
            if (! isset($byPlant[$naam])) {
                $byPlant[$naam] = ['count' => 0, 'empty_count' => 0];
            }
            $byPlant[$naam]['count'] += $count;
            $parsed = self::parseAanbodSearchCode($row['error_code']);
            if ($parsed !== null && $parsed['bucket'] === '0') {
                $byPlant[$naam]['empty_count'] += $count;
            }
        }

        uasort($byPlant, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $out = [];
        foreach (array_slice($byPlant, 0, max(1, $limit), true) as $naam => $stats) {
            $count = $stats['count'];
            $empty = $stats['empty_count'];
            $out[] = [
                'naam' => $naam,
                'count' => $count,
                'empty_count' => $empty,
                'empty_pct' => $count > 0 ? round(($empty / $count) * 100, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * Dimensierijen voor aanbod_search in een periode.
     * Raw events winnen zolang er rijen in het bereik zijn (zoeken sinds laatste
     * aggregate-cron); pas bij lege raw valt terug op conversion_daily.
     *
     * @return list<array{field_name: ?string, error_code: ?string, count: int}>
     */
    private function aanbodSearchDimensionRows(Carbon $from, Carbon $to): array
    {
        if (Schema::hasTable('conversion_events')) {
            $raw = ConversionEvent::query()
                ->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)
                ->whereBetween('occurred_at', [$from->copy()->utc(), $to->copy()->utc()])
                ->selectRaw('field_name, error_code, COUNT(*) as aggregate')
                ->groupBy('field_name', 'error_code')
                ->get();
            if ($raw->isNotEmpty()) {
                return $raw->map(fn ($r) => [
                    'field_name' => $r->field_name !== null ? (string) $r->field_name : null,
                    'error_code' => $r->error_code !== null ? (string) $r->error_code : null,
                    'count' => (int) $r->aggregate,
                ])->all();
            }
        }

        if (! Schema::hasTable('conversion_daily')) {
            return [];
        }

        return ConversionDaily::query()
            ->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('field_name, error_code, SUM(count) as aggregate')
            ->groupBy('field_name', 'error_code')
            ->get()
            ->map(fn ($r) => [
                'field_name' => $r->field_name !== null ? (string) $r->field_name : null,
                'error_code' => $r->error_code !== null ? (string) $r->error_code : null,
                'count' => (int) $r->aggregate,
            ])
            ->all();
    }

    /**
     * @return list<array{field_name: string, error_code: string, count: int}>
     */
    public function topValidationErrors(int $days = 30, int $limit = 15): array
    {
        if (Schema::hasTable('conversion_daily')) {
            $rows = ConversionDaily::query()
                ->where('event_type', ConversionEvent::TYPE_VALIDATION_ERROR)
                ->whereDate('date', '>=', now()->subDays($days - 1)->toDateString())
                ->selectRaw('field_name, error_code, SUM(count) as aggregate')
                ->groupBy('field_name', 'error_code')
                ->orderByDesc('aggregate')
                ->limit($limit)
                ->get();
            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($r) => [
                    'field_name' => (string) $r->field_name,
                    'error_code' => (string) $r->error_code,
                    'count' => (int) $r->aggregate,
                ])->all();
            }
        }

        if (! Schema::hasTable('conversion_events')) {
            return [];
        }

        return ConversionEvent::query()
            ->where('event_type', ConversionEvent::TYPE_VALIDATION_ERROR)
            ->where('occurred_at', '>=', now()->subDays($days - 1))
            ->selectRaw('field_name, error_code, COUNT(*) as aggregate')
            ->groupBy('field_name', 'error_code')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'field_name' => (string) $r->field_name,
                'error_code' => (string) $r->error_code,
                'count' => (int) $r->aggregate,
            ])
            ->all();
    }

    /**
     * @return array{devices: array<string, int>, sources: array<string, int>}
     */
    public function deviceAndSourceBreakdown(int $days = 30): array
    {
        $from = now()->subDays($days - 1);
        $devices = [];
        $sources = [];

        if (Schema::hasTable('conversion_daily')) {
            $deviceRows = ConversionDaily::query()
                ->where('event_type', ConversionEvent::TYPE_REGISTER_VIEW)
                ->whereDate('date', '>=', $from->toDateString())
                ->selectRaw('device_type, SUM(count) as aggregate')
                ->groupBy('device_type')
                ->pluck('aggregate', 'device_type');
            foreach ($deviceRows as $device => $count) {
                $devices[(string) ($device ?: 'onbekend')] = (int) $count;
            }

            $sourceRows = ConversionDaily::query()
                ->where('event_type', ConversionEvent::TYPE_ACCOUNT_CREATED)
                ->whereDate('date', '>=', $from->toDateString())
                ->selectRaw("COALESCE(NULLIF(utm_source, ''), '(geen)') as src, SUM(count) as aggregate")
                ->groupBy('src')
                ->orderByDesc('aggregate')
                ->limit(12)
                ->pluck('aggregate', 'src');
            foreach ($sourceRows as $src => $count) {
                $sources[(string) $src] = (int) $count;
            }
        }

        if ($devices === [] && Schema::hasTable('conversion_events')) {
            $deviceRows = ConversionEvent::query()
                ->where('event_type', ConversionEvent::TYPE_REGISTER_VIEW)
                ->where('occurred_at', '>=', $from)
                ->selectRaw('device_type, COUNT(*) as aggregate')
                ->groupBy('device_type')
                ->pluck('aggregate', 'device_type');
            foreach ($deviceRows as $device => $count) {
                $devices[(string) ($device ?: 'onbekend')] = (int) $count;
            }
        }

        if ($sources === [] && Schema::hasTable('conversion_events')) {
            $sourceRows = ConversionEvent::query()
                ->where('event_type', ConversionEvent::TYPE_ACCOUNT_CREATED)
                ->where('occurred_at', '>=', $from)
                ->selectRaw("COALESCE(NULLIF(utm_source, ''), '(geen)') as src, COUNT(*) as aggregate")
                ->groupBy('src')
                ->orderByDesc('aggregate')
                ->limit(12)
                ->pluck('aggregate', 'src');
            foreach ($sourceRows as $src => $count) {
                $sources[(string) $src] = (int) $count;
            }
        }

        return ['devices' => $devices, 'sources' => $sources];
    }

    /**
     * Conversies per post (utm_content = post id) en per groep (utm_campaign = groep_slug).
     *
     * @return array{by_post: array<int, array<string, int>>, by_groep: array<string, array<string, int>>}
     */
    public function conversionsForPosts(): array
    {
        $types = [
            ConversionEvent::TYPE_CTA_CLICK,
            ConversionEvent::TYPE_REGISTER_VIEW,
            ConversionEvent::TYPE_ACCOUNT_CREATED,
            ConversionEvent::TYPE_EMAIL_VERIFIED,
            ConversionEvent::TYPE_FIRST_OFFER,
        ];
        $byPost = [];
        $byGroep = [];

        $hasRaw = Schema::hasTable('conversion_events')
            && ConversionEvent::query()->whereIn('event_type', $types)->exists();
        $hasDaily = Schema::hasTable('conversion_daily')
            && ConversionDaily::query()->whereIn('event_type', $types)->exists();

        if (! $hasRaw && ! $hasDaily) {
            return ['by_post' => [], 'by_groep' => []];
        }

        // Combineer daily (historie) met raw (recente/nog niet geaggregeerde dagen).
        // Dagen met raw tellen via raw; daily alleen voor dagen zonder raw (geen dubbeltelling).
        $rawDays = $hasRaw ? $this->amsterdamDaysWithRawEvents() : [];

        // Bezoekers = unieke register_view per post/groep (niet som over alle eventtypen).
        if ($hasDaily) {
            $dailyQuery = ConversionDaily::query()->whereIn('event_type', $types);
            $dailyVisitorQuery = ConversionDaily::query()
                ->where('event_type', ConversionEvent::TYPE_REGISTER_VIEW)
                ->where(function ($q): void {
                    $q->whereNotNull('utm_content')->orWhereNotNull('utm_campaign');
                });
            if ($rawDays !== []) {
                $dailyQuery->whereNotIn('date', $rawDays);
                $dailyVisitorQuery->whereNotIn('date', $rawDays);
            }
            $this->mergePostConversionRows(
                $byPost,
                $byGroep,
                $dailyQuery
                    ->selectRaw('utm_campaign, utm_content, event_type, SUM(count) as aggregate')
                    ->groupBy('utm_campaign', 'utm_content', 'event_type')
                    ->get()
            );
            $this->mergePostVisitorRows(
                $byPost,
                $byGroep,
                $dailyVisitorQuery
                    ->selectRaw('utm_campaign, utm_content, SUM(unique_visitors) as aggregate')
                    ->groupBy('utm_campaign', 'utm_content')
                    ->get()
            );
        }

        if ($hasRaw) {
            $this->mergePostConversionRows(
                $byPost,
                $byGroep,
                ConversionEvent::query()
                    ->whereIn('event_type', $types)
                    ->selectRaw('utm_campaign, utm_content, event_type, COUNT(*) as aggregate')
                    ->groupBy('utm_campaign', 'utm_content', 'event_type')
                    ->get()
            );
            $this->mergePostVisitorRows(
                $byPost,
                $byGroep,
                ConversionEvent::query()
                    ->where('event_type', ConversionEvent::TYPE_REGISTER_VIEW)
                    ->where(function ($q): void {
                        $q->whereNotNull('utm_content')->orWhereNotNull('utm_campaign');
                    })
                    ->selectRaw('utm_campaign, utm_content, COUNT(DISTINCT visitor_day_hash) as aggregate')
                    ->groupBy('utm_campaign', 'utm_content')
                    ->get()
            );
        }

        return ['by_post' => $byPost, 'by_groep' => $byGroep];
    }

    /**
     * @param  array<int, array<string, int>>  $byPost
     * @param  array<string, array<string, int>>  $byGroep
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mergePostConversionRows(array &$byPost, array &$byGroep, $rows): void
    {
        foreach ($rows as $row) {
            $content = (string) ($row->utm_content ?? '');
            $campaign = (string) ($row->utm_campaign ?? '');
            $type = (string) $row->event_type;
            $count = (int) $row->aggregate;
            if ($content !== '' && ctype_digit($content)) {
                $id = (int) $content;
                $byPost[$id][$type] = ($byPost[$id][$type] ?? 0) + $count;
            }
            if ($campaign !== '') {
                $byGroep[$campaign][$type] = ($byGroep[$campaign][$type] ?? 0) + $count;
            }
        }
    }

    /**
     * @param  array<int, array<string, int>>  $byPost
     * @param  array<string, array<string, int>>  $byGroep
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function mergePostVisitorRows(array &$byPost, array &$byGroep, $rows): void
    {
        foreach ($rows as $row) {
            $content = (string) ($row->utm_content ?? '');
            $campaign = (string) ($row->utm_campaign ?? '');
            $count = (int) $row->aggregate;
            if ($content !== '' && ctype_digit($content)) {
                $id = (int) $content;
                $byPost[$id]['visitors'] = ($byPost[$id]['visitors'] ?? 0) + $count;
            }
            if ($campaign !== '') {
                $byGroep[$campaign]['visitors'] = ($byGroep[$campaign]['visitors'] ?? 0) + $count;
            }
        }
    }
}
