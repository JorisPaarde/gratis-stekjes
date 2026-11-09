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

    protected $fillable = [
        'posted_on',
        'titel',
        'groep',
        'groep_slug',
        'soort',
        'link',
        'utm_source',
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
     * Relatief pad + query naar /ga/word-lid (eigen domein; veilig voor redirect).
     */
    public function trackingPath(): string
    {
        $query = http_build_query([
            'utm_source' => $this->utm_source ?: 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => $this->groep_slug,
            'utm_content' => (string) $this->id,
        ]);

        return '/ga/word-lid?'.$query;
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
