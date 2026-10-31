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
        'plaats',
        'provincie',
        'organisator',
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
}
