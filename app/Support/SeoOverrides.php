<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lees SEO-overrides uit config/seo.php (slug-keyed, geen DB).
 */
final class SeoOverrides
{
    public static function organisatieTitle(string $slug, string $fallback): string
    {
        $title = trim((string) config("seo.organisaties.{$slug}.seo_title", ''));

        return $title !== '' ? $title : $fallback;
    }

    public static function organisatieDescription(string $slug, string $fallback): string
    {
        $description = trim((string) config("seo.organisaties.{$slug}.seo_description", ''));

        return $description !== '' ? $description : $fallback;
    }

    /**
     * Gerelateerde organisatie-link voor een stadspagina.
     *
     * @return array{organisatie_slug: string, anchor: string, intro: string}|null
     */
    public static function stadRelatedOrganisatie(string $stadSlug): ?array
    {
        $row = config("seo.stad_related_organisatie.{$stadSlug}");
        if (! is_array($row)) {
            return null;
        }

        $orgSlug = trim((string) ($row['organisatie_slug'] ?? ''));
        $anchor = trim((string) ($row['anchor'] ?? ''));
        $intro = trim((string) ($row['intro'] ?? ''));
        if ($orgSlug === '' || $anchor === '' || $intro === '') {
            return null;
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $orgSlug)) {
            return null;
        }

        return [
            'organisatie_slug' => $orgSlug,
            'anchor' => $anchor,
            'intro' => $intro,
        ];
    }
}
