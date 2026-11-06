<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Models\Concerns\HasSafeEncryptedAttributes;
use App\Notifications\ResetPasswordDutch;
use App\Notifications\VerifyEmailDutch;
use App\Support\Country;
use App\Support\Naam;
use App\Support\PostalCode;
use App\Support\SafeMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

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
        'postcode_gebied',
        'woonplaats',
        'lat',
        'lon',
        'country',
        'berichtmail',
        'adres_straat',
        'adres_huisnummer',
        'adres_postcode',
        'adres_plaats',
        'adres_delen',
        'struikrover',
        'stekchallenge',
        'acquisition_landing_path',
        'acquisition_referrer_host',
        'acquisition_utm_source',
        'acquisition_utm_medium',
        'acquisition_utm_campaign',
        'acquisition_utm_content',
        'acquisition_found_via',
        'acquisition_found_via_other',
        'conversion_flow_id',
        'conversion_flow_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
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
            'welcome_shown_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_stats_viewer' => 'boolean',
            'password' => 'hashed',
            'active' => 'boolean',
            'berichtmail' => 'boolean',
            'telefoon' => SafeEncrypted::class,
            'adres_straat' => SafeEncrypted::class,
            'adres_huisnummer' => SafeEncrypted::class,
            'adres_postcode' => SafeEncrypted::class,
            'adres_plaats' => SafeEncrypted::class,
            'adres_delen' => 'boolean',
            'struikrover' => 'boolean',
            'stekchallenge' => 'boolean',
            'last_seen_at' => 'datetime',
            'conversion_flow_expires_at' => 'datetime',
            'verification_reminder_sent_at' => 'datetime',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    /** Stats-lezer-bot: geen lidtellingen, geen publieke lijsten, geen aanbod. */
    public function isStatsViewer(): bool
    {
        return (bool) ($this->is_stats_viewer ?? false);
    }

    /**
     * Leden voor tellingen/mijlpalen: stats_viewer-accounts tellen niet mee.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeMembers(Builder $query): Builder
    {
        if (Schema::hasColumn('users', 'is_stats_viewer')) {
            $query->where('is_stats_viewer', false);
        }

        return $query;
    }

    /**
     * Naam zoals anderen hem zien. Een e-mailadres als naam (oude accounts) wordt nooit getoond.
     * Lege of ontbrekende naam → veilige tijdelijke weergave «Lid».
     */
    public function publiekeNaam(): string
    {
        $naam = trim((string) $this->name);
        if ($naam === '' || Naam::lijktOpEmail($naam, (string) $this->email)) {
            return 'Lid';
        }

        return $naam;
    }

    /** Heeft het lid zelf een voornaam/bijnaam gekozen (geen lege naam, geen e-mail-als-naam). */
    public function heeftGekozenNaam(): bool
    {
        $naam = trim((string) $this->name);

        return $naam !== '' && ! Naam::lijktOpEmail($naam, (string) $this->email);
    }

    /** Nieuwe eenmalige token voor bevestigingslink (en herinnering). */
    public function rotateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $this->forceFill(['email_verification_token' => $token])->save();

        return $token;
    }

    public function clearEmailVerificationToken(): void
    {
        if ($this->email_verification_token !== null) {
            $this->forceFill(['email_verification_token' => null])->save();
        }
    }

    /**
     * Atomisch token verbruiken (compare-and-clear). Precies één parallelle request wint.
     */
    public function claimEmailVerificationToken(string $token): bool
    {
        if ($token === '' || $this->id === null) {
            return false;
        }

        $affected = static::query()
            ->whereKey($this->id)
            ->where('email_verification_token', $token)
            ->update(['email_verification_token' => null]);

        if ($affected === 1) {
            $this->email_verification_token = null;

            return true;
        }

        return false;
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

    /**
     * Geldige postcode: zelfde 0.65.0-regel (NL 4 cijfers + optioneel 2 letters, BE 4 cijfers)
     * én een plaatslabel met geocode. Centraal zodat lookup-search dit later meeneemt.
     */
    public function hasValidPostcode(): bool
    {
        return $this->postcodeValidityReason() === null;
    }

    /**
     * Waarom de postcode (nog) niet geldig is voor buurtzoeken.
     *
     * @return null|'leeg'|'ongeldig_formaat'|'geen_plaats'
     */
    public function postcodeValidityReason(): ?string
    {
        $country = Country::normalize((string) ($this->country ?? Country::NL));
        $gebied = trim((string) ($this->postcode_gebied ?? ''));
        $label = trim((string) ($this->locatie_label ?? ''));

        $kandidaat = $gebied;
        if ($kandidaat === '' && $label !== '' && PostalCode::looksLikePostcode($label, $country)) {
            $kandidaat = $label;
        }

        if ($kandidaat === '') {
            return 'leeg';
        }

        if (PostalCode::validate($kandidaat, $country) !== true) {
            return 'ongeldig_formaat';
        }

        if (! $this->hasLocation()) {
            return 'geen_plaats';
        }

        return null;
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
