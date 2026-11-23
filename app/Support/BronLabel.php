<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Label voor bron_url-links: Website / Meer informatie / Bron.
 *
 * Overzichts-URL’s (gedeeld door >1 plek, of bekend overzichtspad) heten nooit «Website».
 */
final class BronLabel
{
    public const WEBSITE = 'Website';

    public const MEER_INFO = 'Meer informatie';

    public const BRON = 'Bron';

    /**
     * @param  array<string, int>|null  $urlCounts  bron_url → aantal plekken (zichtbaar of JSON)
     */
    public static function for(
        ?string $bronUrl,
        string $naam = '',
        bool $evenement = false,
        ?array $urlCounts = null,
    ): string {
        if ($evenement) {
            return self::BRON;
        }

        $url = trim((string) $bronUrl);
        if ($url === '') {
            return self::MEER_INFO;
        }

        if (self::isOverviewUrl($url, $urlCounts)) {
            return self::BRON;
        }

        if (self::looksLikeOwnSite($url, $naam)) {
            return self::WEBSITE;
        }

        return self::MEER_INFO;
    }

    /**
     * @param  array<string, int>|null  $urlCounts
     */
    public static function isOverviewUrl(string $url, ?array $urlCounts = null): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if ($urlCounts !== null && ($urlCounts[$url] ?? 0) > 1) {
            return true;
        }

        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        // Bekende overzichtspaden / landelijke lijsten.
        if (preg_match('#/(locaties|locations)(/|$)#', $path) === 1) {
            return true;
        }
        if (str_contains($path, '/zadenbibliotheken-in-')) {
            return true;
        }
        if (str_contains($path, '/services/grainotheques')) {
            return true;
        }
        if (str_ends_with(rtrim($path, '/'), '/zadenbib')) {
            return true;
        }
        if ($host === 'zadenbibnoordlimburg.be') {
            return true;
        }
        // Andere bekende multi-plek overzichten in onze data.
        if (str_contains($path, '/plantenasielen')) {
            return true;
        }
        if (str_contains($path, 'plantenbieb-plantenasiel')) {
            return true;
        }

        return false;
    }

    public static function looksLikeOwnSite(string $url, string $naam): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? '';
        $hostKern = preg_replace('/[^a-z0-9]/', '', (string) strstr($host.'.', '.', true));
        $naamNorm = preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($naam)));
        $generiek = ['facebook', 'instagram', 'google', 'nextdoor', 'linktree', 'bing', 'cylex'];
        if ($hostKern === '' || $naamNorm === '' || in_array($hostKern, $generiek, true)) {
            return false;
        }
        if (str_contains($naamNorm, $hostKern) || str_contains($hostKern, $naamNorm)) {
            return true;
        }

        // Strenger dan voorheen (>= 8 tekens): min. 10 overlappinge tekens én ≥ 65% match.
        $pct = 0.0;
        $overlap = similar_text($naamNorm, $hostKern, $pct);

        return $overlap >= 10 && $pct >= 65.0;
    }
}
