<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ErvaringFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ervaring extends Model
{
    /** @use HasFactory<ErvaringFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'ervaringen';

    protected $fillable = [
        'user_id',
        'body',
        'photo_path',
        'plantensoort_id',
        'aanbod_id',
        'conversation_id',
        'plaatsnaam',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'plantensoort_id' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aanbod(): BelongsTo
    {
        return $this->belongsTo(Aanbod::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function hasPhoto(): bool
    {
        return trim((string) $this->photo_path) !== '';
    }
}
