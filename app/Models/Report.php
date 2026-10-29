<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'conversation_id',
        'reason',
        'handled',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'handled' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
