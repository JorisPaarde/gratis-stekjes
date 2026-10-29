<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AanbodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aanbod extends Model
{
    /** @use HasFactory<AanbodFactory> */
    use HasFactory;

    protected $table = 'aanbod';

    protected $fillable = [
        'user_id',
        'type',
        'tekst',
        'visible',
        'expires_at',
        'expiry_reminder_sent_at',
        'renew_token',
        'renew_token_expires',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'expires_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'renew_token_expires' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query
            ->where('visible', true)
            ->where('expires_at', '>', now());
    }
}
