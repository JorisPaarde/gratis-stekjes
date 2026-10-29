<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Deelplek extends Model
{
    protected $table = 'deelplekken';

    protected $fillable = [
        'slug',
        'naam',
        'type',
        'plaats',
        'provincie',
        'adres',
        'lat',
        'lng',
        'bron_url',
        'geraadpleegd',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'geraadpleegd' => 'date',
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
}
