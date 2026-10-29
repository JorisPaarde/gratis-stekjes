<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Deelplek extends Model
{
    public const SOURCE_SEED = 'seed';

    public const SOURCE_AANMELDING = 'aanmelding';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'deelplekken';

    protected $fillable = [
        'slug',
        'naam',
        'type',
        'plaats',
        'provincie',
        'adres',
        'lat',
        'lng',
        'bron_url',
        'geraadpleegd',
        'visible',
        'source',
        'status',
        'beschrijving',
        'contact_email',
        'privacy_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'geraadpleegd' => 'date',
            'visible' => 'boolean',
            'contact_email' => 'encrypted',
            'privacy_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true)->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('source', self::SOURCE_AANMELDING);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->visible;
    }
}
