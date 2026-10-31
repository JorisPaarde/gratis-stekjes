<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordDutch;
use App\Notifications\VerifyEmailDutch;
use App\Support\SafeMail;
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
        'pending_email',
        'password',
        'active',
        'telefoon',
        'locatie_label',
        'lat',
        'lon',
        'country',
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
            'telefoon' => 'encrypted',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        SafeMail::notify($this, new VerifyEmailDutch);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        SafeMail::notify($this, new ResetPasswordDutch($token));
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

    public function hasPendingEmail(): bool
    {
        return trim((string) $this->pending_email) !== '';
    }

    public function aanbod(): HasMany
    {
        return $this->hasMany(Aanbod::class);
    }

    public function ervaringen(): HasMany
    {
        return $this->hasMany(Ervaring::class);
    }

    public function milestonesSent(): HasMany
    {
        return $this->hasMany(MilestoneSent::class);
    }

    public function mailLogs(): HasMany
    {
        return $this->hasMany(MailLog::class);
    }

    public function latestMailLog(): ?MailLog
    {
        return $this->mailLogs()->orderByDesc('id')->first();
    }
}
