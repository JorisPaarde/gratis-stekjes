<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normaliseer plaats-/gemeentenamen naar een stabiele URL-slug.
 * Accenten en apostroffen vallen weg, zodat «Haarlem» en «haarlem» dezelfde sleutel krijgen.
 */
final class PlaatsSlug
{
    public static function from(string $naam): string
    {
        $naam = trim($naam);
        if ($naam === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($naam, \Normalizer::FORM_D);
            if (! is_string($normalized)) {
                $normalized = $naam;
            }
            $normalized = preg_replace('/\p{Mn}/u', '', $normalized) ?? $normalized;
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $naam);
            $normalized = is_string($converted) ? $converted : $naam;
        }
        $normalized = str_replace(["'", "'", '`', '´'], '', $normalized);
        $slug = strtolower(trim($normalized));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return self::applyAlias($slug);
    }

    public static function applyAlias(string $slug): string
    {
        if ($slug === '') {
            return '';
        }

        /** @var array<string, string> $aliases */
        $aliases = config('gratisplanten.plaats_aliases', []);
        $aliases = array_change_key_case($aliases, CASE_LOWER);

        return $aliases[$slug] ?? $slug;
    }
}
