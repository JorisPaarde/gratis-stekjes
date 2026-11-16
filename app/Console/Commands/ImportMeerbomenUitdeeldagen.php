<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Evenement;
use App\Services\Evenementen;
use App\Support\Geo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotente import van Meer Bomen Nu-uitdeeldagen (type bomenhub).
 * Upsert-sleutel: bron-URL (url/bron) + start_datum. Standaard dry-run.
 */
class ImportMeerbomenUitdeeldagen extends Command
{
    protected $signature = 'gp:import-meerbomen-uitdeeldagen
        {--apply : Schrijf/werk evenementen bij (zonder deze vlag alleen dry-run)}
        {--file= : Pad naar JSON (default: data/imports/meerbomen-uitdeeldagen-2026-11-16.json)}';

    protected $description = 'Importeer Meer Bomen Nu-uitdeeldagen in evenementen (idempotent)';

    public function handle(Evenementen $evenementen): int
    {
        $apply = (bool) $this->option('apply');
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
            ? 'Schrijven: evenementen worden geüpdatet of toegevoegd.'
            : 'Dry-run: er wordt niets opgeslagen.');
        $this->line('Bestand: '.$path);

        $nieuw = 0;
        $bijgewerkt = 0;
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

            $existing = $this->findByBronUrlAndDatum($parsed['url'], $parsed['start_datum']);
            $status = $existing ? 'bestaat → bijwerken' : 'nieuw';
            $this->line(sprintf(
                '%s · %s · %s · %s%s',
                $parsed['start_datum'],
                $parsed['plaats'] !== '' ? $parsed['plaats'] : '(geen plaats)',
                $parsed['titel'],
                $status,
                $parsed['start_tijd'] !== '' ? ' · '.$parsed['start_tijd'].($parsed['eind_tijd'] !== '' ? '–'.$parsed['eind_tijd'] : '') : '',
            ));

            if (! $apply) {
                if ($existing) {
                    $bijgewerkt++;
                } else {
                    $nieuw++;
                }

                continue;
            }

            $fields = [
                'titel' => $parsed['titel'],
                'type' => $parsed['type'],
                'start_datum' => $parsed['start_datum'],
                'eind_datum' => $parsed['eind_datum'],
                'start_tijd' => $parsed['start_tijd'],
                'eind_tijd' => $parsed['eind_tijd'],
                'plaats' => $parsed['plaats'],
                'adres' => $parsed['adres'],
                'provincie' => $parsed['provincie'],
                'organisator' => $parsed['organisator'],
                'omschrijving' => $parsed['omschrijving'],
                'organisatie_slug' => $parsed['organisatie_slug'],
                'url' => $parsed['url'],
                'bron' => $parsed['bron'],
                'gecontroleerd_op' => $parsed['gecontroleerd_op'],
                'visible' => true,
            ];
            if ($parsed['lat'] !== null) {
                $fields['lat'] = $parsed['lat'];
            }
            if ($parsed['lon'] !== null) {
                $fields['lon'] = $parsed['lon'];
            }

            $result = $evenementen->save($existing?->id, $fields);
            if (empty($result['ok'])) {
                $this->error('  opslaan mislukt: '.($result['error'] ?? 'onbekend'));
                $fout++;

                continue;
            }

            if ($existing) {
                $bijgewerkt++;
            } else {
                $nieuw++;
            }
        }

        $this->newLine();
        $this->info('Aantallen:');
        $this->line('  nieuw: '.$nieuw);
        $this->line('  bijgewerkt/bestaat: '.$bijgewerkt);
        if ($fout > 0) {
            $this->line('  fout/ongeldig: '.$fout);
        }

        Log::info('gp:import-meerbomen-uitdeeldagen klaar', [
            'apply' => $apply,
            'nieuw' => $nieuw,
            'bijgewerkt' => $bijgewerkt,
            'fout' => $fout,
            'file' => basename($path),
        ]);

        return $fout > 0 && $nieuw === 0 && $bijgewerkt === 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolvePath(string $file): ?string
    {
        $file = trim($file);
        if ($file === '') {
            $file = 'data/imports/meerbomen-uitdeeldagen-2026-11-16.json';
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
     *   start_tijd: string,
     *   eind_tijd: string,
     *   plaats: string,
     *   adres: string,
     *   provincie: string,
     *   organisator: string,
     *   omschrijving: string,
     *   organisatie_slug: string,
     *   url: string,
     *   bron: string,
     *   gecontroleerd_op: string,
     *   lat: ?float,
     *   lon: ?float
     * }|null
     */
    private function parseActie(array $actie): ?array
    {
        $titel = trim(mb_substr((string) ($actie['titel'] ?? ''), 0, 200));
        $plaats = trim(mb_substr((string) ($actie['plaats'] ?? ''), 0, 120));
        $adres = trim(mb_substr((string) ($actie['adres'] ?? ''), 0, 255));
        $provincie = trim((string) ($actie['provincie'] ?? ''));
        $url = trim(mb_substr((string) ($actie['url'] ?? ''), 0, 500));
        $bron = trim(mb_substr((string) ($actie['bron'] ?? $url), 0, 500));
        $datum = trim((string) ($actie['start_datum'] ?? $actie['datum'] ?? ''));
        $type = trim((string) ($actie['type'] ?? 'bomenhub'));
        $organisator = trim(mb_substr((string) ($actie['organisator'] ?? 'Meer Bomen Nu'), 0, 160));
        $omschrijving = trim(mb_substr((string) ($actie['omschrijving'] ?? ''), 0, 500));
        $slug = trim(mb_substr((string) ($actie['organisatie_slug'] ?? ''), 0, 80));
        $startTijd = $this->normalizeTime((string) ($actie['start_tijd'] ?? ''));
        $eindTijd = $this->normalizeTime((string) ($actie['eind_tijd'] ?? ''));

        if ($titel === '' || $plaats === '' || $url === '' || $datum === '' || $provincie === '') {
            $this->warn('Actie onvolledig (titel/plaats/url/datum/provincie verplicht).');

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

        $lat = isset($actie['lat']) && is_numeric($actie['lat']) ? Geo::roundCoord((float) $actie['lat']) : null;
        $lon = isset($actie['lon']) && is_numeric($actie['lon']) ? Geo::roundCoord((float) $actie['lon']) : null;

        return [
            'titel' => $titel,
            'type' => $type !== '' ? $type : 'bomenhub',
            'start_datum' => $datum,
            'eind_datum' => trim((string) ($actie['eind_datum'] ?? '')),
            'start_tijd' => $startTijd,
            'eind_tijd' => $eindTijd,
            'plaats' => $plaats,
            'adres' => $adres,
            'provincie' => $provincie,
            'organisator' => $organisator !== '' ? $organisator : 'Meer Bomen Nu',
            'omschrijving' => $omschrijving !== ''
                ? $omschrijving
                : 'Gratis boompjes en struiken van Meer Bomen Nu, ophalen na aanmelding.',
            'organisatie_slug' => $slug,
            'url' => $url,
            'bron' => $bron !== '' ? $bron : $url,
            'gecontroleerd_op' => trim((string) ($actie['gecontroleerd_op'] ?? '2026-11-16')) ?: '2026-11-16',
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    private function normalizeTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '';
    }

    private function findByBronUrlAndDatum(string $url, string $datum): ?Evenement
    {
        return Evenement::query()
            ->whereDate('start_datum', $datum)
            ->where(function ($q) use ($url): void {
                $q->where('url', $url)->orWhere('bron', $url);
            })
            ->first();
    }
}
