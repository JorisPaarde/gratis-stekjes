<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversionDaily extends Model
{
    public $timestamps = false;

    protected $table = 'conversion_daily';

    protected $fillable = [
        'date',
        'week_key',
        'event_type',
        'device_type',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'field_name',
        'error_code',
        'count',
        'unique_visitors',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'count' => 'integer',
            'unique_visitors' => 'integer',
        ];
    }
}
