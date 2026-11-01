<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Models\Concerns\HasSafeEncryptedAttributes;
use App\Notifications\ResetPasswordDutch;
use App\Notifications\VerifyEmailDutch;
use App\Support\Naam;
use App\Support\SafeMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasSafeEncryptedAttributes, Notifiable;

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
        'berichtmail',
        'adres_straat',
        'adres_huisnummer',
        'adres_postcode',
        'adres_plaats',
        'adres_delen',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'telefoon',
        'adres_straat',
        'adres_huisnummer',
        'adres_postcode',
        'adres_plaats',
    ];

    /** @var list<string> */
    public const ADRES_VELDEN = ['adres_straat', 'adres_huisnummer', 'adres_postcode', 'adres_plaats'];

    /** Verenigingen waar dit lid bij hoort (ook nog niet goedgekeurde). */
    public function verenigingen(): BelongsToMany
    {
        return $this->belongsToMany(Vereniging::class, 'vereniging_user')
            ->withPivot(['zichtbaar', 'created_at']);
    }

    /** Goedgekeurde verenigingen die het lid openbaar wil tonen. */
    public function zichtbareVerenigingen(): BelongsToMany
    {
        return $this->verenigingen()
            ->wherePivot('zichtbaar', true)
            ->where('verenigingen.status', Vereniging::STATUS_APPROVED)
            ->orderBy('verenigingen.naam');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'active' => 'boolean',
            'berichtmail' => 'boolean',
            'telefoon' => SafeEncrypted::class,
            'adres_straat' => SafeEncrypted::class,
            'adres_huisnummer' => SafeEncrypted::class,
            'adres_postcode' => SafeEncrypted::class,
            'adres_plaats' => SafeEncrypted::class,
            'adres_delen' => 'boolean',
            'last_seen_at' => 'datetime',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    /**
     * Naam zoals anderen hem zien. Een e-mailadres als naam (oude accounts) wordt nooit getoond.
     */
    public function publiekeNaam(): string
    {
        $naam = trim((string) $this->name);
        if ($naam === '' || Naam::lijktOpEmail($naam, (string) $this->email)) {
            return 'Lid';
        }

        return $naam;
    }

    /** Volledig adres op één regel, of null als het (nog) niet is ingevuld of niet leesbaar. */
    public function volledigAdres(): ?string
    {
        foreach (self::ADRES_VELDEN as $veld) {
            if ($this->isUnreadable($veld)) {
                return null;
            }
        }
        $straat = trim((string) ($this->adres_straat ?? ''));
        $nummer = trim((string) ($this->adres_huisnummer ?? ''));
        $postcode = trim((string) ($this->adres_postcode ?? ''));
        $plaats = trim((string) ($this->adres_plaats ?? ''));
        if ($straat === '' || $nummer === '' || $plaats === '') {
            return null;
        }

        return trim($straat.' '.$nummer).', '.trim($postcode.' '.$plaats);
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
