<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\VerifyEmailDutch;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'telefoon',
        'locatie_label',
        'lat',
        'lon',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'telefoon',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailDutch);
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null
            && $this->lon !== null
            && trim((string) $this->locatie_label) !== '';
    }

    public function aanbod(): HasMany
    {
        return $this->hasMany(Aanbod::class);
    }
}
