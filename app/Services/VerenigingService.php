<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\User;
use App\Models\Vereniging;
use App\Notifications\VerenigingAangemeld;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lidmaatschap van verenigingen (volkstuin, tuinvereniging, buurttuin …).
 * Nieuwe verenigingen van leden wachten op goedkeuring; lidmaatschap is pas
 * openbaar als het lid dat aanzet.
 */
final class VerenigingService
{
    public const MAX_PER_LID = 5;

    public function __construct(
        private readonly Organisaties $organisaties,
    ) {}

    public static function beschikbaar(): bool
    {
        return Schema::hasTable('verenigingen') && Schema::hasTable('vereniging_user');
    }

    /**
     * Keuzelijst: goedgekeurde verenigingen + organisaties uit de vaste lijst.
     *
     * @return list<array{label: string, naam: string, plaats: string}>
     */
    public function opties(): array
    {
        $opties = [];
        $gezien = [];
        foreach (Vereniging::query()->approved()->orderBy('naam')->get() as $v) {
            $label = self::label($v->naam, $v->plaats);
            $gezien[mb_strtolower($label)] = true;
            if ($v->json_slug !== null) {
                $gezien['json:'.$v->json_slug] = true;
            }
            $opties[] = ['label' => $label, 'naam' => $v->naam, 'plaats' => $v->plaats];
        }
        foreach ($this->organisaties->all() as $org) {
            $label = self::label((string) $org['naam'], (string) ($org['plaats'] ?? ''));
            if (isset($gezien['json:'.$org['slug']]) || isset($gezien[mb_strtolower($label)])) {
                continue;
            }
            $opties[] = ['label' => $label, 'naam' => (string) $org['naam'], 'plaats' => (string) ($org['plaats'] ?? '')];
        }
        usort($opties, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $opties;
    }

    public static function label(string $naam, string $plaats): string
    {
        $plaats = trim($plaats);

        return trim($naam).($plaats !== '' && $plaats !== 'Nederland' ? ' — '.$plaats : '');
    }

    /**
     * Koppel een lid aan een vereniging uit de lijst, of maak een nieuwe aan (wacht op controle).
     *
     * @return array{ok: bool, error?: string, vereniging?: Vereniging, nieuw?: bool}
     */
    public function koppel(User $user, string $invoer, ?string $plaats, ?string $type): array
    {
        $invoer = trim(preg_replace('/\s+/', ' ', $invoer) ?? '');
        if ($invoer === '') {
            return ['ok' => false, 'error' => 'Vul de naam van je vereniging in.'];
        }
        if ($user->verenigingen()->count() >= self::MAX_PER_LID) {
            return ['ok' => false, 'error' => 'Je kunt bij maximaal '.self::MAX_PER_LID.' verenigingen horen.'];
        }

        $vereniging = $this->zoekBestaande($invoer, $plaats);
        $nieuw = false;

        if ($vereniging === null) {
            $plaats = trim((string) $plaats);
            $type = (string) $type;
            if (mb_strlen($invoer) < 3 || mb_strlen($invoer) > 160) {
                return ['ok' => false, 'error' => 'Gebruik een naam van 3 tot 160 tekens.'];
            }
            if ($plaats === '' || mb_strlen($plaats) > 120) {
                return ['ok' => false, 'error' => 'Staat je vereniging nog niet in de lijst? Vul dan ook de plaats in.'];
            }
            if (! array_key_exists($type, Vereniging::TYPES)) {
                return ['ok' => false, 'error' => 'Kies wat voor vereniging het is.'];
            }
            $vereniging = Vereniging::query()->create([
                'slug' => $this->uniekeSlug($invoer.' '.$plaats),
                'naam' => $invoer,
                'plaats' => $plaats,
                'type' => $type,
                'status' => Vereniging::STATUS_PENDING,
                'created_by' => $user->id,
            ]);
            $nieuw = true;
        }

        if ($vereniging->status === Vereniging::STATUS_HIDDEN) {
            return ['ok' => false, 'error' => 'Deze vereniging kan ik nu niet koppelen. Neem contact op als dat niet klopt.'];
        }

        $user->verenigingen()->syncWithoutDetaching([$vereniging->id => ['zichtbaar' => false]]);

        if ($nieuw) {
            $admin = trim((string) config('stekjes.admin_email', ''));
            if ($admin !== '') {
                SafeMail::toAddress($admin, new VerenigingAangemeld($vereniging));
            }
        }

        return ['ok' => true, 'vereniging' => $vereniging, 'nieuw' => $nieuw];
    }

    /** Zoek op «Naam — Plaats» uit de keuzelijst, of op naam (+ plaats) bij een bestaande. */
    private function zoekBestaande(string $invoer, ?string $plaats): ?Vereniging
    {
        $naam = $invoer;
        $labelPlaats = null;
        if (str_contains($invoer, ' — ')) {
            [$naam, $labelPlaats] = array_map('trim', explode(' — ', $invoer, 2));
        }
        $plaats = trim((string) ($labelPlaats ?? $plaats ?? ''));

        $kandidaten = Vereniging::query()
            ->where('status', '!=', Vereniging::STATUS_HIDDEN)
            ->whereRaw('LOWER(naam) = ?', [mb_strtolower($naam)])
            ->get();
        if ($kandidaten->count() === 1 && ($plaats === '' || strcasecmp($kandidaten[0]->plaats, $plaats) === 0)) {
            return $kandidaten[0];
        }
        foreach ($kandidaten as $k) {
            if ($plaats !== '' && strcasecmp($k->plaats, $plaats) === 0) {
                return $k;
            }
        }

        // Organisatie uit de vaste lijst: maak de koppelrij aan (direct goedgekeurd).
        foreach ($this->organisaties->all() as $org) {
            if (strcasecmp((string) $org['naam'], $naam) !== 0) {
                continue;
            }
            $orgPlaats = (string) ($org['plaats'] ?? '');
            if ($plaats !== '' && $orgPlaats !== '' && $orgPlaats !== 'Nederland' && strcasecmp($orgPlaats, $plaats) !== 0) {
                continue;
            }

            return Vereniging::query()->firstOrCreate(
                ['json_slug' => (string) $org['slug']],
                [
                    'slug' => (string) $org['slug'],
                    'naam' => (string) $org['naam'],
                    'plaats' => $orgPlaats !== '' ? $orgPlaats : 'Nederland',
                    'type' => array_key_exists((string) ($org['type'] ?? ''), Vereniging::TYPES) ? (string) $org['type'] : 'anders',
                    'status' => Vereniging::STATUS_APPROVED,
                ]
            );
        }

        return null;
    }

    private function uniekeSlug(string $basis): string
    {
        $slug = Str::slug($basis) ?: 'vereniging';
        $slug = mb_substr($slug, 0, 100);
        $kandidaat = $slug;
        $i = 2;
        while (Vereniging::query()->where('slug', $kandidaat)->exists() || $this->organisaties->find($kandidaat) !== null) {
            $kandidaat = $slug.'-'.$i;
            $i++;
        }

        return $kandidaat;
    }

    public function zetZichtbaar(User $user, int $verenigingId, bool $zichtbaar): void
    {
        $user->verenigingen()->updateExistingPivot($verenigingId, ['zichtbaar' => $zichtbaar]);
    }

    public function ontkoppel(User $user, int $verenigingId): void
    {
        $user->verenigingen()->detach($verenigingId);
    }

    /** Goedgekeurde vereniging bij een publieke slug (of null). */
    public function publiek(string $slug): ?Vereniging
    {
        if (! self::beschikbaar()) {
            return null;
        }

        return Vereniging::query()->approved()
            ->where(fn ($q) => $q->where('slug', $slug)->orWhere('json_slug', $slug))
            ->first();
    }

    /**
     * Openbaar blok voor de verenigingspagina: aantal leden en aanbod van leden die
     * hun lidmaatschap tonen. Geen namen van leden zonder aanbod.
     *
     * @return array{leden: int, aanbod: list<array<string, mixed>>}
     */
    public function ledenBlok(Vereniging $vereniging, AanbodSearch $search): array
    {
        try {
            $leden = $vereniging->leden()->count();
            $aanbod = Aanbod::query()
                ->publiclyListed()
                ->with(['user', 'fotos'])
                ->whereHas('user', function ($q) use ($vereniging): void {
                    $q->where('active', true)
                        ->whereNotNull('email_verified_at')
                        ->whereHas('verenigingen', fn ($v) => $v
                            ->where('verenigingen.id', $vereniging->id)
                            ->where('vereniging_user.zichtbaar', true));
                })
                ->latest('created_at')
                ->limit(30)
                ->get()
                ->map(fn (Aanbod $a) => $search->toPublicItem($a))
                ->all();
        } catch (Throwable) {
            return ['leden' => 0, 'aanbod' => []];
        }

        return ['leden' => $leden, 'aanbod' => $aanbod];
    }
}
