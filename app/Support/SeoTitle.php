<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Document-title: optioneel " · {site}" alleen als het geheel ≤ 60 tekens blijft.
 */
final class SeoTitle
{
    public static function document(?string $pageTitle, ?string $siteName = null): string
    {
        $site = $siteName ?? (string) config('site.name', 'Gratis planten');
        $page = trim((string) $pageTitle);
        if ($page === '') {
            return $site;
        }

        $suffix = ' · '.$site;
        if (mb_strlen($page.$suffix, 'UTF-8') <= 60) {
            return $page.$suffix;
        }

        return $page;
    }

    public static function fitsWithBrand(string $pageTitle, ?string $siteName = null): bool
    {
        $site = $siteName ?? (string) config('site.name', 'Gratis planten');
        $page = trim($pageTitle);

        return $page !== '' && mb_strlen($page.' · '.$site, 'UTF-8') <= 60;
    }
}
