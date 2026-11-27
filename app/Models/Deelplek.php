<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Models\Concerns\HasSafeEncryptedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Deelplek extends Model
{
    use HasSafeEncryptedAttributes;

    public const SOURCE_SEED = 'seed';

    public const SOURCE_AANMELDING = 'aanmelding';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const OPEN_ALTIJD = 'altijd';

    public const OPEN_DAGEN = 'dagen';

    public const OPEN_SEIZOEN = 'seizoen';

    protected $table = 'deelplekken';

    protected $fillable = [
        'slug',
        'naam',
        'type',
        'plaats',
        'provincie',
        'country',
        'adres',
        'lat',
        'lng',
        'bron_url',
        'geraadpleegd',
        'visible',
        'source',
        'status',
        'beschrijving',
        'open_keuze',
        'open_tekst',
        'contact_email',
        'privacy_at',
        'reviewed_at',
        'approval_mailed_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'geraadpleegd' => 'date',
            'visible' => 'boolean',
            'contact_email' => SafeEncrypted::class,
            'privacy_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approval_mailed_at' => 'datetime',
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

    /** Label voor beheerlijsten: In beoordeling, Afgewezen, Zichtbaar of Verborgen. */
    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_PENDING) {
            return 'In beoordeling';
        }
        if ($this->status === self::STATUS_REJECTED) {
            return 'Afgewezen';
        }

        return $this->getAttribute('visible') ? 'Zichtbaar' : 'Verborgen';
    }

    /** CSS-modifier bij {@see statusLabel()} (kleur naast tekst, zodat chips onderscheidbaar zijn). */
    public function statusChipClass(): string
    {
        if ($this->status === self::STATUS_PENDING) {
            return 'status--wacht';
        }
        if ($this->status === self::STATUS_REJECTED) {
            return 'status--fout';
        }

        return $this->getAttribute('visible') ? 'status--ok' : 'status--uit';
    }

    /** @return list<string> */
    public static function openKeuzes(): array
    {
        return [self::OPEN_ALTIJD, self::OPEN_DAGEN, self::OPEN_SEIZOEN];
    }

    /**
     * Openingskeuze uit het aanmeldformulier.
     *
     * @param  array<string, mixed>  $fields
     * @return array{open_keuze: ?string, open_tekst: string}
     */
    public static function openFromAanmelding(array $fields): array
    {
        $keuze = trim((string) ($fields['open_keuze'] ?? ''));
        if (! in_array($keuze, self::openKeuzes(), true)) {
            return ['open_keuze' => null, 'open_tekst' => ''];
        }
        $tekst = '';
        if ($keuze === self::OPEN_DAGEN) {
            $tekst = trim(mb_substr((string) ($fields['open_tekst_dagen'] ?? ''), 0, 200));
        } elseif ($keuze === self::OPEN_SEIZOEN) {
            $tekst = trim(mb_substr((string) ($fields['open_tekst_seizoen'] ?? ''), 0, 200));
        }

        return ['open_keuze' => $keuze, 'open_tekst' => $tekst];
    }

    /** Eén regel voor bezoekers en beheer, of null als er niets is ingevuld. */
    public function openRegel(): ?string
    {
        $keuze = (string) ($this->open_keuze ?? '');
        $tekst = trim((string) ($this->open_tekst ?? ''));

        return match ($keuze) {
            self::OPEN_ALTIJD => 'Open: altijd',
            self::OPEN_DAGEN => $tekst !== '' ? 'Open: '.$tekst : 'Open: op vaste dagen',
            self::OPEN_SEIZOEN => $tekst !== '' ? 'Open: '.$tekst : 'Open: alleen in een seizoen',
            default => null,
        };
    }
}
