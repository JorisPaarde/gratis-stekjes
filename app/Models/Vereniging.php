<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vereniging extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_HIDDEN = 'hidden';

    /** @var array<string, string> */
    public const TYPES = [
        'volkstuin' => 'Volkstuinvereniging',
        'tuinvereniging' => 'Tuinvereniging',
        'buurttuin' => 'Buurt- of gemeenschapstuin',
        'schooltuin' => 'Schooltuin',
        'plantenclub' => 'Planten- of zadenclub',
        'anders' => 'Anders',
    ];

    protected $table = 'verenigingen';

    protected $fillable = [
        'slug',
        'naam',
        'plaats',
        'type',
        'json_slug',
        'status',
        'created_by',
    ];

    public function leden(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vereniging_user')
            ->withPivot(['zichtbaar', 'created_at']);
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES['anders'];
    }

    public function url(): string
    {
        return route('organisaties.show', ['slug' => $this->slug]);
    }
}
