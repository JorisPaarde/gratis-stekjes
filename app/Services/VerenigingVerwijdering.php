<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vereniging;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Definitief verwijderen van verborgen verenigingen van leden.
 *
 * Alleen status «hidden» zonder json_slug. Een vereniging met json_slug hoort bij de vaste
 * lijst (data/organisaties.json) en zou bij de volgende koppeling weer worden aangemaakt;
 * die blijft dus verborgen. Verenigingen worden niet geseed, dus een tombstone is niet nodig.
 * Publieke pagina's bestaan alleen voor goedgekeurde verenigingen, dus er blijven geen
 * dode links of sitemap-regels achter.
 */
final class VerenigingVerwijdering
{
    public static function verwijderbaar(Vereniging $vereniging): bool
    {
        return $vereniging->status === Vereniging::STATUS_HIDDEN && $vereniging->json_slug === null;
    }

    /** Uitleg waarom een vereniging niet verwijderd kan worden (of null als het wel kan). */
    public static function weigerReden(Vereniging $vereniging): ?string
    {
        if ($vereniging->status !== Vereniging::STATUS_HIDDEN) {
            return 'Alleen verborgen verenigingen kun je verwijderen. Verberg de vereniging eerst.';
        }
        if ($vereniging->json_slug !== null) {
            return 'Deze vereniging hoort bij de vaste lijst en blijft verborgen. Verwijderen zou haar bij een nieuwe koppeling weer terugzetten.';
        }

        return null;
    }

    /**
     * @param  list<int|string>  $ids
     * @return Collection<int, Vereniging>
     */
    public function findDeletableByIds(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return collect();
        }

        return Vereniging::query()
            ->whereIn('id', $ids)
            ->where('status', Vereniging::STATUS_HIDDEN)
            ->whereNull('json_slug')
            ->withCount('leden')
            ->orderBy('naam')
            ->get();
    }

    /**
     * @param  Collection<int, Vereniging>  $verenigingen
     * @return array{ok: bool, error?: string, deleted?: list<array{id: int, naam: string, plaats: string}>}
     */
    public function deleteMany(Collection $verenigingen): array
    {
        if ($verenigingen->isEmpty()) {
            return ['ok' => false, 'error' => 'Geen verenigingen geselecteerd.'];
        }

        foreach ($verenigingen as $vereniging) {
            $reden = self::weigerReden($vereniging);
            if ($reden !== null) {
                return ['ok' => false, 'error' => $reden];
            }
        }

        $deleted = [];

        DB::transaction(function () use ($verenigingen, &$deleted): void {
            foreach ($verenigingen as $vereniging) {
                $meta = [
                    'id' => (int) $vereniging->id,
                    'naam' => (string) $vereniging->naam,
                    'plaats' => (string) $vereniging->plaats,
                ];
                // Alleen de koppelingen met leden gaan weg; de ledenaccounts blijven bestaan.
                $vereniging->leden()->detach();
                $vereniging->delete();
                $deleted[] = $meta;
            }
        });

        foreach ($deleted as $meta) {
            Log::info('beheer.vereniging.deleted', [
                'vereniging_id' => $meta['id'],
                'at' => now()->toIso8601String(),
            ]);
        }

        return ['ok' => true, 'deleted' => $deleted];
    }
}
