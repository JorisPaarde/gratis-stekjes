<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'aanbod_id',
        'user_a_id',
        'user_b_id',
        'contact_a',
        'contact_b',
    ];

    public function aanbod(): BelongsTo
    {
        return $this->belongsTo(Aanbod::class);
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function involves(int $userId): bool
    {
        return (int) $this->user_a_id === $userId || (int) $this->user_b_id === $userId;
    }

    public function otherUserId(int $viewerId): int
    {
        return (int) $this->user_a_id === $viewerId
            ? (int) $this->user_b_id
            : (int) $this->user_a_id;
    }

    public function myContactChoice(int $viewerId): ?string
    {
        return (int) $this->user_a_id === $viewerId
            ? $this->contact_a
            : $this->contact_b;
    }

    public function hasMutualContact(): bool
    {
        return $this->contact_a === 'ja' && $this->contact_b === 'ja';
    }
}
