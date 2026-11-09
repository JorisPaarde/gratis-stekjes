<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBotDaily extends Model
{
    public $timestamps = false;

    protected $table = 'form_bot_daily';

    protected $fillable = [
        'date',
        'form',
        'reason',
        'count',
    ];

    protected function casts(): array
    {
        // Geen date-cast: op SQLite zet Eloquent anders "Y-m-d 00:00:00"
        // waardoor unique(date,form,reason) naast "Y-m-d" een tweede rij toelaat.
        return [
            'count' => 'integer',
        ];
    }
}
