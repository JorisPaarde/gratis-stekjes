<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deelplek;
use App\Models\PartnerMarker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Data voor het blok «Hier kun je nu al terecht» op de homepage.
 *
 * Begrensd: maximaal {@see self::AANBOD_LIMIT} aanbiedingen, plekken alleen als
 * aantallen per soort. Werkt zonder tabellen (homepage moet ook zonder DB laden).
 */
final class HomeOverzicht
{
    public const AANBOD_LIMIT = 6;

    /** Meervoudsvormen voor de tellingen («55 zaadbibliotheken»). */
    private const MEERVOUD = [
        'plantenasiel' => 'plantenasiels',
        'plantenbieb' => 'plantenbiebs',
        'ruilkast' => 'ruilkasten',
        'stekjesruilpunt' => 'stekjesruilpunten',
        'zaadbibliotheek' => 'zaadbibliotheken',
        'voedselbos' => 'voedselbossen',
        'tuinvereniging' => 'tuinverenigingen',
        'partner' => 'initiatieven',
    ];

    public function __construct(
        private readonly AanbodSearch $search,
        private readonly Deelplekken $deelplekken,
        private readonly PartnerMarkers $partners,
    ) {}

    /**
     * @return array{
     *   aanbod: list<array<string, mixed>>,
     *   plekken: list<array{type: string, label: string, aantal: int, url: string}>,
     *   plekken_totaal: int
     * }
     */
    public function data(): array
    {
        $counts = $this->countByType(Deelplek::class, ['deelplekken']);
        foreach ($this->countByType(PartnerMarker::class, ['partner_markers']) as $type => $aantal) {
            $counts[$type] = ($counts[$type] ?? 0) + $aantal;
        }

        $labels = $this->deelplekken->typeLabels() + $this->partners->typeLabels();
        $plekken = [];
        foreach ($counts as $type => $aantal) {
            if ($aantal < 1 || ! isset($labels[$type])) {
                continue;
            }
            $plekken[] = [
                'type' => $type,
                'label' => $this->label($type, $labels[$type], $aantal),
                'aantal' => $aantal,
                'url' => $this->deelplekken->typeValid($type)
                    ? route('deelplekken', ['type' => $type])
                    : route('partners'),
            ];
        }
        usort($plekken, static fn (array $a, array $b) => $b['aantal'] <=> $a['aantal']);

        return [
            'aanbod' => $this->recentAanbod(),
            'plekken' => $plekken,
            'plekken_totaal' => array_sum(array_column($plekken, 'aantal')),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function recentAanbod(): array
    {
        if (! $this->tablesExist(['aanbod', 'users'])) {
            return [];
        }

        try {
            return $this->search->recent(self::AANBOD_LIMIT);
        } catch (Throwable $e) {
            Log::warning('Home: recent aanbod laden mislukt', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  class-string<Deelplek|PartnerMarker>  $model
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function countByType(string $model, array $tables): array
    {
        if (! $this->tablesExist($tables)) {
            return [];
        }

        try {
            return $model::query()->visible()
                ->selectRaw('type, COUNT(*) as aantal')
                ->groupBy('type')
                ->pluck('aantal', 'type')
                ->map(static fn ($n) => (int) $n)
                ->all();
        } catch (Throwable $e) {
            Log::warning('Home: plekken tellen mislukt', ['model' => $model, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function label(string $type, string $enkelvoud, int $aantal): string
    {
        if ($aantal === 1) {
            return mb_strtolower($enkelvoud, 'UTF-8');
        }

        return self::MEERVOUD[$type] ?? mb_strtolower($enkelvoud, 'UTF-8');
    }

    /** @param  list<string>  $tables */
    private function tablesExist(array $tables): bool
    {
        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
