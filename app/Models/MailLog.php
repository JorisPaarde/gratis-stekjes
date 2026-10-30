<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailLog extends Model
{
    public $timestamps = false;

    protected $table = 'mail_log';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }
}
