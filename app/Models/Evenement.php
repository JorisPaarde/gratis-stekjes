<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Evenement extends Model
{
    protected $table = 'evenementen';

    protected $fillable = [
        'titel',
        'type',
        'start_datum',
        'eind_datum',
        'start_tijd',
        'eind_tijd',
        'plaats',
        'adres',
        'lat',
        'lon',
        'provincie',
        'organisator',
        'omschrijving',
        'organisatie_slug',
        'url',
        'bron',
        'gecontroleerd_op',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'start_datum' => 'date',
            'eind_datum' => 'date',
            'gecontroleerd_op' => 'date',
            'visible' => 'boolean',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInProvincie(Builder $query, string $provincie): Builder
    {
        return $query->where('provincie', $provincie);
    }

    /**
     * Tijdelijke plekken op de deelplekkenkaart: zichtbaar, met coördinaten,
     * nog niet voorbij, en start binnen één maand (Europe/Amsterdam).
     */
    public function scopeOpKaart(Builder $query, ?CarbonInterface $today = null): Builder
    {
        $today ??= Carbon::now('Europe/Amsterdam')->startOfDay();
        $horizon = $today->copy()->addMonth()->startOfDay();
        $todayStr = $today->toDateString();
        $horizonStr = $horizon->toDateString();

        return $query->visible()
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->whereDate('start_datum', '<=', $horizonStr)
            ->where(function (Builder $q) use ($todayStr): void {
                $q->where(function (Builder $inner) use ($todayStr): void {
                    $inner->whereNotNull('eind_datum')
                        ->whereDate('eind_datum', '>=', $todayStr);
                })->orWhere(function (Builder $inner) use ($todayStr): void {
                    $inner->whereNull('eind_datum')
                        ->whereDate('start_datum', '>=', $todayStr);
                });
            });
    }

    /** Laatste dag van het evenement (einddatum of startdatum). */
    public function effectiveEndDate(): CarbonInterface
    {
        /** @var Carbon $start */
        $start = $this->start_datum;

        return $this->eind_datum ?? $start;
    }

    public function isPast(?CarbonInterface $today = null): bool
    {
        $today ??= Carbon::now('Europe/Amsterdam')->startOfDay();

        return $this->effectiveEndDate()->lt($today);
    }

    public function isUpcoming(?CarbonInterface $today = null): bool
    {
        return ! $this->isPast($today);
    }

    /** HH:MM of leeg (negeert seconden uit TIME-kolom). */
    public function startTijdHm(): string
    {
        return self::timeToHm($this->attributes['start_tijd'] ?? null);
    }

    public function eindTijdHm(): string
    {
        return self::timeToHm($this->attributes['eind_tijd'] ?? null);
    }

    private static function timeToHm(mixed $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '';
    }
}
