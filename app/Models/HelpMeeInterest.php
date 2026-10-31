<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpMeeInterest extends Model
{
    protected $table = 'help_mee_interests';

    protected $fillable = [
        'name',
        'email',
        'note',
        'privacy_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'note' => 'encrypted',
            'privacy_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
