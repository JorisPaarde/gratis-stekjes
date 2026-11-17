<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deelplek;
use App\Models\DeelplekTombstone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Definitief verwijderen van verborgen deelplekken (+ tombstone zodat de seeder
 * ze niet terugzet). Geen FK-relaties op deelplekken; contact_email zit op de rij.
 */
final class DeelplekVerwijdering
{
    public function __construct(
        private readonly StadCatalogus $stadCatalogus,
    ) {}

    /**
     * @return array{ok: bool, error?: string, deleted?: list<array{id: int, slug: string, naam: string, plaats: string}>}
     */
    public function deleteOne(Deelplek $plek): array
    {
        return $this->deleteMany(collect([$plek]));
    }

    /**
     * @param  Collection<int, Deelplek>  $plekken
     * @return array{ok: bool, error?: string, deleted?: list<array{id: int, slug: string, naam: string, plaats: string}>}
     */
    public function deleteMany(Collection $plekken): array
    {
        if ($plekken->isEmpty()) {
            return ['ok' => false, 'error' => 'Geen deelplekken geselecteerd.'];
        }

        foreach ($plekken as $plek) {
            if ($plek->visible) {
                return [
                    'ok' => false,
                    'error' => 'Zichtbare deelplekken kun je niet verwijderen. Verberg ze eerst of kies alleen verborgen plekken.',
                ];
            }
        }

        if (! Schema::hasTable('deelplek_tombstones')) {
            return ['ok' => false, 'error' => 'Verwijderen is nog niet beschikbaar (tombstone-tabel ontbreekt).'];
        }

        $deleted = [];

        DB::transaction(function () use ($plekken, &$deleted): void {
            foreach ($plekken as $plek) {
                $slug = (string) $plek->slug;
                $meta = [
                    'id' => (int) $plek->id,
                    'slug' => $slug,
                    'naam' => (string) $plek->naam,
                    'plaats' => (string) $plek->plaats,
                ];

                DeelplekTombstone::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'naam' => mb_substr((string) $plek->naam, 0, 160),
                        'plaats' => mb_substr((string) $plek->plaats, 0, 120),
                        'deleted_at' => now(),
                    ]
                );

                $plek->delete();
                $deleted[] = $meta;
            }
        });

        $this->stadCatalogus->forgetCache();

        foreach ($deleted as $meta) {
            Log::info('beheer.deelplek.deleted', [
                'deelplek_id' => $meta['id'],
                'slug' => $meta['slug'],
                'at' => now()->toIso8601String(),
            ]);
        }

        return ['ok' => true, 'deleted' => $deleted];
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Deelplek>
     */
    public function findHiddenByIds(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return collect();
        }

        return Deelplek::query()
            ->whereIn('id', $ids)
            ->where('visible', false)
            ->orderBy('naam')
            ->get();
    }
}
