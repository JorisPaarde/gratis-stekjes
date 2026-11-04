<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Seizoensblok «Maak er zelf meer van»: per maand wat je in je eigen tuin kunt doen (eerst voor jezelf),
 * en daarna delen. Data: data/seizoen.json; plantkaarten uit {@see NuTeStekken}.
 */
final class Seizoen
{
    /** Pas vanaf dit aantal aanbiedingen deze maand tonen we de teller. */
    public const TELLER_VANAF = 5;

    public function __construct(
        private readonly NuTeStekken $kalender,
        private readonly ?string $jsonPath = null,
    ) {}

    public function huidigeMaand(): int
    {
        return (int) Carbon::now(NuTeStekken::TIMEZONE)->format('n');
    }

    /**
     * @return array{maand: int, maand_naam: string, seizoen: string, kop: string, tekst: string, wist_je_dat: string, acties: list<array{titel: string, tekst: string, url: string, illustratie: string}>, planten: list<array<string, mixed>>, gedeeld: int|null}|null
     */
    public function blok(?int $maand = null): ?array
    {
        $maand ??= $this->huidigeMaand();
        if ($maand < 1 || $maand > 12) {
            $maand = $this->huidigeMaand();
        }

        $rij = $this->load()['maanden'][(string) $maand] ?? null;
        if (! is_array($rij) || empty($rij['acties'])) {
            return null;
        }

        $acties = [];
        foreach ($rij['acties'] as $actie) {
            $slug = (string) ($actie['slug'] ?? '');
            $acties[] = [
                'titel' => (string) ($actie['titel'] ?? ''),
                'tekst' => (string) ($actie['tekst'] ?? ''),
                'url' => $slug !== '' ? route('stektips.artikel', ['slug' => $slug]) : route('vermeerderen'),
                'illustratie' => (string) ($actie['illustratie'] ?? 'kopstek'),
            ];
        }

        $planten = [];
        try {
            $planten = array_slice($this->kalender->uitgelicht($this->kalender->voorMaand($maand)['groepen']), 0, 6);
        } catch (Throwable) {
            $planten = [];
        }

        return [
            'maand' => $maand,
            'maand_naam' => $this->kalender->maandnaam($maand),
            'seizoen' => (string) ($rij['seizoen'] ?? 'lente'),
            'kop' => (string) ($rij['kop'] ?? ''),
            'tekst' => (string) ($rij['tekst'] ?? ''),
            'wist_je_dat' => (string) ($rij['wist_je_dat'] ?? ''),
            'acties' => $acties,
            'planten' => $planten,
            'gedeeld' => $this->gedeeldDezeMaand(),
        ];
    }

    /** Aantal keer iets aangeboden deze kalendermaand, alleen als het een leuk getal is. */
    private function gedeeldDezeMaand(): ?int
    {
        try {
            if (! Schema::hasTable('aanbod')) {
                return null;
            }
            $start = Carbon::now(NuTeStekken::TIMEZONE)->startOfMonth()->utc();
            $aantal = Aanbod::query()->where('type', 'uitdelen')->where('created_at', '>=', $start)->count();
        } catch (Throwable) {
            return null;
        }

        return $aantal >= self::TELLER_VANAF ? $aantal : null;
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        $path = $this->jsonPath ?? base_path('data/seizoen.json');
        if (! is_readable($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
