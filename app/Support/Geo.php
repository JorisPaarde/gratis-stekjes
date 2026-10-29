<?php

declare(strict_types=1);

namespace App\Support;

final class Geo
{
    public static function roundCoord(float $value): float
    {
        return round($value, (int) config('stekjes.coord_decimals', 3));
    }

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lon2 - $lon1);
        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $r * $c;
    }

    public static function normalizeQuery(string $q): string
    {
        $q = trim(preg_replace('/\s+/', ' ', $q) ?? $q);

        return mb_strtolower($q, 'UTF-8');
    }
}
