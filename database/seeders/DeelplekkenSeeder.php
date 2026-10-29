<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Deelplek;
use App\Services\Deelplekken;
use Illuminate\Database\Seeder;
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

        foreach ($data['plekken'] as $row) {
            $slug = (string) $row['id'];
            $slugs[] = $slug;
            Deelplek::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'naam' => trim(mb_substr((string) $row['naam'], 0, 160)),
                    'type' => (string) $row['type'],
                    'plaats' => trim(mb_substr((string) $row['plaats'], 0, 120)),
                    'provincie' => trim(mb_substr((string) $row['provincie'], 0, 64)),
                    'adres' => trim(mb_substr((string) ($row['adres'] ?? ''), 0, 255)),
                    'lat' => (float) $row['lat'],
                    'lng' => (float) $row['lng'],
                    'bron_url' => trim(mb_substr((string) $row['bron_url'], 0, 500)),
                    'geraadpleegd' => (string) $row['geraadpleegd'],
                    'visible' => true,
                ]
            );
        }

        if ($slugs !== []) {
            Deelplek::query()->whereNotIn('slug', $slugs)->delete();
        }
    }
}
