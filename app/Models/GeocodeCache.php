<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodeCache extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'geocode_cache';

    protected $primaryKey = 'query_key';

    protected $keyType = 'string';

    protected $fillable = [
        'query_key',
        'lat',
        'lon',
        'label',
        'cached_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
            'cached_at' => 'datetime',
        ];
    }
}
