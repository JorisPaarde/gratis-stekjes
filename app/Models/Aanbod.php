<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AanbodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aanbod extends Model
{
    /** @use HasFactory<AanbodFactory> */
    use HasFactory;

    protected $table = 'aanbod';

    protected $fillable = [
        'user_id',
        'type',
        'tekst',
        'plantensoort_id',
        'visible',
        'expires_at',
        'expiry_reminder_sent_at',
        'renew_token',
        'renew_token_expires',
    ];

    protected function casts(): array
    {
        return [
            'plantensoort_id' => 'integer',
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

    public function fotos(): HasMany
    {
        return $this->hasMany(AanbodFoto::class, 'aanbod_id')->orderBy('sort_order')->orderBy('id');
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
