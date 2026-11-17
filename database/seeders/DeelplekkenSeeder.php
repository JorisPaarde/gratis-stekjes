<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Deelplek;
use App\Models\DeelplekTombstone;
use App\Services\Deelplekken;
use App\Support\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DeelplekkenSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(Deelplekken::class);
        $errors = $service->validateJson();
        if ($errors !== []) {
            throw new RuntimeException('Deelplekken-JSON ongeldig: '.implode('; ', $errors));
        }

        $data = $service->loadJson();
        $slugs = [];
        $tombstones = Schema::hasTable('deelplek_tombstones')
            ? DeelplekTombstone::query()->pluck('slug')->all()
            : [];
        $tombstoneSet = array_fill_keys($tombstones, true);

        foreach ($data['plekken'] as $row) {
            $slug = (string) $row['id'];

            // Definitief verwijderd via beheer: niet opnieuw aanmaken bij deploy-seed.
            if (isset($tombstoneSet[$slug])) {
                Deelplek::query()->where('slug', $slug)->delete();

                continue;
            }

            $slugs[] = $slug;
            $country = Country::normalize(isset($row['country']) ? (string) $row['country'] : Country::NL);
            $visible = array_key_exists('visible', $row)
                ? filter_var($row['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true;
            if ($visible === null) {
                $visible = (bool) $row['visible'];
            }

            // Bewust verborgen seed-rijen (o.a. Happy stekkie Groesbeek) niet opnieuw openzetten.
            $existing = Deelplek::query()->where('slug', $slug)->first();
            if ($existing !== null && ! $existing->visible) {
                $visible = false;
            }
            if (array_key_exists('visible', $row)
                && filter_var($row['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false) {
                $visible = false;
            }

            Deelplek::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'naam' => trim(mb_substr((string) $row['naam'], 0, 160)),
                    'type' => (string) $row['type'],
                    'plaats' => trim(mb_substr((string) $row['plaats'], 0, 120)),
                    'provincie' => trim(mb_substr((string) $row['provincie'], 0, 64)),
                    'country' => $country,
                    'adres' => trim(mb_substr((string) ($row['adres'] ?? ''), 0, 255)),
                    'lat' => (float) $row['lat'],
                    'lng' => (float) $row['lng'],
                    'bron_url' => trim(mb_substr((string) $row['bron_url'], 0, 500)),
                    'geraadpleegd' => (string) $row['geraadpleegd'],
                    'visible' => $visible,
                    'source' => Deelplek::SOURCE_SEED,
                    'status' => Deelplek::STATUS_APPROVED,
                ]
            );
        }

        // Alleen geseede rijen opruimen; goedgekeurde/afgewezen aanmeldingen blijven staan.
        // Tombstone-slugs staan niet in $slugs → eventuele restanten verdwijnen hier ook.
        if ($slugs !== []) {
            Deelplek::query()
                ->where('source', Deelplek::SOURCE_SEED)
                ->whereNotIn('slug', $slugs)
                ->delete();
        }
    }
}
