<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PostLog extends Model
{
    /**
     * Leesbaar alfabet zonder verwarrende tekens (geen 0/O/1/I/L).
     * 31 tekens → 5 posities ≈ 28M codes.
     */
    public const SHORT_CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const SHORT_CODE_LENGTH = 5;

    /** Standaard doel na /s/{code} wanneer target_path leeg is. */
    public const DEFAULT_TARGET_PATH = '/ga/word-lid';

    /** Max lengte opgeslagen intern pad (kolom target_path). */
    public const TARGET_PATH_MAX = 200;

    protected $fillable = [
        'posted_on',
        'titel',
        'groep',
        'groep_slug',
        'soort',
        'link',
        'utm_source',
        'target_path',
        'notitie',
        // short_code: alleen via creating-boot / migratie-backfill, niet mass-assign.
    ];

    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PostLog $post): void {
            if ($post->short_code === null || $post->short_code === '') {
                $post->short_code = static::generateUniqueShortCode();
            } else {
                $normalized = static::normalizeShortCode((string) $post->short_code);
                $post->short_code = $normalized ?? static::generateUniqueShortCode();
            }
        });
    }

    public static function slugForGroep(string $groep): string
    {
        $slug = Str::slug(trim($groep));

        return $slug !== '' ? Str::limit($slug, 140, '') : 'groep';
    }

    /**
     * Normaliseer invoer naar uppercase alfabet-code, of null bij ongeldig.
     */
    public static function normalizeShortCode(string $code): ?string
    {
        $code = strtoupper(trim($code));
        $len = strlen($code);
        if ($len < 4 || $len > 8) {
            return null;
        }

        $allowed = self::SHORT_CODE_ALPHABET;
        for ($i = 0; $i < $len; $i++) {
            if (! str_contains($allowed, $code[$i])) {
                return null;
            }
        }

        return $code;
    }

    public static function generateUniqueShortCode(int $length = self::SHORT_CODE_LENGTH): string
    {
        $alphabet = self::SHORT_CODE_ALPHABET;
        $alphabetLen = strlen($alphabet);
        $maxAttempts = 32;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, $alphabetLen - 1)];
            }

            $exists = DB::table('post_logs')->where('short_code', $code)->exists();
            if (! $exists) {
                return $code;
            }
        }

        throw new RuntimeException('Kon geen unieke short_code genereren.');
    }

    /**
     * Alleen interne relatieve paden: begint met `/`, geen `//`, geen schema/host.
     * Lege string → null (betekent: gebruik DEFAULT_TARGET_PATH).
     * Ongeldig → null (caller moet 422 geven wanneer invoer niet-leeg was).
     */
    public static function normalizeTargetPath(?string $candidate): ?string
    {
        $path = trim((string) $candidate);
        if ($path === '') {
            return null;
        }

        if (strlen($path) > self::TARGET_PATH_MAX) {
            return null;
        }

        // Geen schema, protocol-relatief, backslash of control chars.
        if (
            str_starts_with($path, '//')
            || str_contains($path, '\\')
            || str_contains($path, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            return null;
        }

        // Schemes zonder slash (javascript:, data:) en absolute URLs.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1) {
            return null;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        // Geen query/fragment in opgeslagen pad — UTM wordt apart geplakt.
        if (str_contains($path, '?') || str_contains($path, '#')) {
            return null;
        }

        // Geen `:` in pad (voorkomt /javascript:…-achtige trucs).
        if (str_contains($path, ':')) {
            return null;
        }

        // Alleen veilige padtekens (Unicode-letters/cijfers, streepjes, underscore, slash, punt, tilde).
        if (preg_match('#^/[\p{L}\p{N}\-._~/]*$#u', $path) !== 1) {
            return null;
        }

        // Collapse dubbele slashes (na //evil-check); blijft met / beginnen.
        $normalized = (string) preg_replace('#/{2,}#', '/', $path);
        if ($normalized === '' || ! str_starts_with($normalized, '/') || str_starts_with($normalized, '//')) {
            return null;
        }

        // Geen trailing slash behalve root.
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    /**
     * Opgelost doelpad voor redirect/tracking (nooit leeg; fallback DEFAULT).
     */
    public function resolvedTargetPath(): string
    {
        $stored = trim((string) ($this->target_path ?? ''));
        if ($stored === '') {
            return self::DEFAULT_TARGET_PATH;
        }

        return static::normalizeTargetPath($stored) ?? self::DEFAULT_TARGET_PATH;
    }

    /**
     * Relatief pad + UTM-query op eigen domein (veilig voor /s/-redirect).
     */
    public function trackingPath(): string
    {
        $query = http_build_query([
            'utm_source' => $this->utm_source ?: 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => $this->groep_slug,
            'utm_content' => (string) $this->id,
        ]);

        return $this->resolvedTargetPath().'?'.$query;
    }

    public function trackingUrl(?string $base = null): string
    {
        $baseUrl = rtrim($base ?: (string) config('app.url'), '/');

        return $baseUrl.$this->trackingPath();
    }

    public function shortUrl(?string $base = null): string
    {
        $baseUrl = rtrim($base ?: (string) config('app.url'), '/');
        $code = (string) ($this->short_code ?? '');

        return $baseUrl.'/s/'.$code;
    }
}
