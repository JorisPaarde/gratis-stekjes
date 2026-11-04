<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Evenement;
use App\Services\Evenementen;
use App\Services\LocationGeocoder;
use App\Support\Country;
use App\Support\Geo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Eenmalige import van vastgelegde Struikroven-agenda-JSON naar evenementen.
 * Standaard dry-run; schrijven alleen met --apply. Idempotent op bron-URL + datum.
 */
class ImportStruikrovenAgenda extends Command
{
    protected $signature = 'gp:import-struikroven-agenda
        {--apply : Schrijf nieuwe evenementen (zonder deze vlag alleen dry-run)}
        {--file= : Pad naar JSON (default: data/imports/struikroven-agenda-2026-11-04.json)}
        {--geocode : Plaats/adres geocoderen voor lat/lon (standaard aan bij --apply)}
        {--no-geocode : Sla geocoding over}';

    protected $description = 'Importeer vastgelegde Struikroven-agenda-JSON in evenementen (idempotent)';

    public function handle(Evenementen $evenementen, LocationGeocoder $geocoder): int
    {
        $apply = (bool) $this->option('apply');
        $doGeocode = $apply && ! $this->option('no-geocode');
        if ($this->option('geocode')) {
            $doGeocode = true;
        }

        $path = $this->resolvePath((string) ($this->option('file') ?: ''));
        if ($path === null || ! is_file($path)) {
            $this->error('JSON-bestand niet gevonden: '.($path ?? '(leeg)'));

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('Kon JSON niet lezen: '.$path);

            return self::FAILURE;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error('Ongeldige JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($decoded) || ! isset($decoded['acties']) || ! is_array($decoded['acties'])) {
            $this->error('JSON mist een acties-array.');

            return self::FAILURE;
        }

        $this->info($apply
            ? 'Schrijven: nieuwe evenementen worden toegevoegd.'
            : 'Dry-run: er wordt niets opgeslagen.');
        $this->line('Bestand: '.$path);

        $nieuw = 0;
        $bestaat = 0;
        $fout = 0;

        foreach ($decoded['acties'] as $index => $actie) {
            if (! is_array($actie)) {
                $this->warn("Actie #{$index}: overgeslagen (geen object).");
                $fout++;

                continue;
            }

            $parsed = $this->parseActie($actie);
            if ($parsed === null) {
                $fout++;

                continue;
            }

            $exists = $this->existsByBronUrlAndDatum($parsed['url'], $parsed['start_datum']);
            $status = $exists ? 'bestaat al' : 'nieuw';
            $this->line(sprintf(
                '%s · %s · %s · %s',
                $parsed['start_datum'],
                $parsed['plaats'] !== '' ? $parsed['plaats'] : '(geen plaats)',
                $parsed['titel'],
                $status,
            ));

            if ($exists) {
                $bestaat++;

                continue;
            }

            $nieuw++;
            if (! $apply) {
                continue;
            }

            $result = $evenementen->save(null, [
                'titel' => $parsed['titel'],
                'type' => $parsed['type'],
                'start_datum' => $parsed['start_datum'],
                'eind_datum' => $parsed['eind_datum'],
                'plaats' => $parsed['plaats'],
                'provincie' => $parsed['provincie'],
                'organisator' => $parsed['organisator'],
                'organisatie_slug' => $parsed['organisatie_slug'],
                'url' => $parsed['url'],
                'bron' => $parsed['url'],
                'gecontroleerd_op' => $parsed['gecontroleerd_op'],
                'visible' => true,
            ]);

            if (empty($result['ok'])) {
                $this->error('  opslaan mislukt: '.($result['error'] ?? 'onbekend'));
                $fout++;
                $nieuw--;

                continue;
            }

            $id = (int) ($result['id'] ?? 0);
            if ($doGeocode && $id > 0) {
                $this->geocodeEvenement($id, $parsed, $geocoder);
            }
        }

        $this->newLine();
        $this->info('Aantallen:');
        $this->line('  nieuw: '.$nieuw);
        $this->line('  bestaat al: '.$bestaat);
        if ($fout > 0) {
            $this->line('  fout/ongeldig: '.$fout);
        }

        Log::info('gp:import-struikroven-agenda klaar', [
            'apply' => $apply,
            'nieuw' => $nieuw,
            'bestaat' => $bestaat,
            'fout' => $fout,
            'file' => basename($path),
        ]);

        return $fout > 0 && $nieuw === 0 && $bestaat === 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolvePath(string $file): ?string
    {
        $file = trim($file);
        if ($file === '') {
            $file = 'data/imports/struikroven-agenda-2026-11-04.json';
        }

        if ($file[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $file) === 1) {
            return $file;
        }

        return base_path($file);
    }

    /**
     * @param  array<string, mixed>  $actie
     * @return array{
     *   titel: string,
     *   type: string,
     *   start_datum: string,
     *   eind_datum: string,
     *   plaats: string,
     *   adres: string,
     *   provincie: string,
     *   organisator: string,
     *   organisatie_slug: string,
     *   url: string,
     *   gecontroleerd_op: string
     * }|null
     */
    private function parseActie(array $actie): ?array
    {
        $titel = trim(mb_substr((string) ($actie['titel'] ?? ''), 0, 200));
        $plaats = trim(mb_substr((string) ($actie['plaats'] ?? ''), 0, 120));
        $adres = trim(mb_substr((string) ($actie['adres'] ?? ''), 0, 200));
        $provincie = trim((string) ($actie['provincie'] ?? ''));
        $url = trim(mb_substr((string) ($actie['url'] ?? ''), 0, 500));
        $datum = trim((string) ($actie['datum'] ?? ''));
        $type = trim((string) ($actie['type'] ?? 'overig'));
        $organisator = trim(mb_substr((string) ($actie['organisator'] ?? 'Struikroven'), 0, 160));
        $slug = trim(mb_substr((string) ($actie['organisatie_slug'] ?? 'struikroven'), 0, 80));

        if ($titel === '' || $plaats === '' || $url === '' || $datum === '' || $provincie === '') {
            $this->warn('Actie onvolledig (titel/plaats/url/datum/provincie verplicht): '.json_encode([
                'titel' => $titel,
                'plaats' => $plaats,
                'url' => $url,
                'datum' => $datum,
            ], JSON_UNESCAPED_UNICODE));

            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            $this->warn('Ongeldige datum: '.$datum);

            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $this->warn('Ongeldige URL: '.$url);

            return null;
        }

        return [
            'titel' => $titel,
            'type' => $type !== '' ? $type : 'overig',
            'start_datum' => $datum,
            'eind_datum' => '',
            'plaats' => $plaats,
            'adres' => $adres,
            'provincie' => $provincie,
            'organisator' => $organisator !== '' ? $organisator : 'Struikroven',
            'organisatie_slug' => $slug !== '' ? $slug : 'struikroven',
            'url' => $url,
            'gecontroleerd_op' => '2026-11-04',
        ];
    }

    private function existsByBronUrlAndDatum(string $url, string $datum): bool
    {
        return Evenement::query()
            ->whereDate('start_datum', $datum)
            ->where(function ($q) use ($url): void {
                $q->where('url', $url)->orWhere('bron', $url);
            })
            ->exists();
    }

    /**
     * @param  array{
     *   plaats: string,
     *   adres: string
     * }  $parsed
     */
    private function geocodeEvenement(int $id, array $parsed, LocationGeocoder $geocoder): void
    {
        if (! Schema::hasColumn('evenementen', 'lat') || ! Schema::hasColumn('evenementen', 'lon')) {
            return;
        }

        $query = $parsed['adres'] !== '' ? $parsed['adres'] : $parsed['plaats'];
        if ($query === '') {
            return;
        }

        try {
            $hit = $geocoder->resolve($query, Country::NL, true);
        } catch (Throwable $e) {
            Log::warning('gp:import-struikroven-agenda geocode-fout', [
                'evenement_id' => $id,
                'land' => Country::NL,
                'invoer_type' => $parsed['adres'] !== '' ? 'adres' : 'plaats',
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (empty($hit['ok'])) {
            Log::warning('gp:import-struikroven-agenda geocode-geen-hit', [
                'evenement_id' => $id,
                'land' => Country::NL,
                'invoer_type' => $parsed['adres'] !== '' ? 'adres' : 'plaats',
            ]);

            return;
        }

        $row = Evenement::query()->find($id);
        if ($row === null) {
            return;
        }

        $row->update([
            'lat' => Geo::roundCoord((float) $hit['lat']),
            'lon' => Geo::roundCoord((float) $hit['lon']),
        ]);
    }
}
