<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LocationGeocoder;
use App\Support\Country;
use App\Support\PostalCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Normaliseer bestaande locatie_labels naar postcode_gebied + woonplaats + openbaar label.
 * Standaard --dry-run (schrijft niets). Zonder --dry-run schrijft alleen bij wijziging.
 * Geen e-mail, naam of adres in de uitvoer.
 */
class NormaliseerLocaties extends Command
{
    protected $signature = 'gp:normaliseer-locaties
        {--dry-run : Toon wat er zou gebeuren zonder te schrijven (standaard aan tenzij --no-dry-run)}
        {--no-dry-run : Schrijf wijzigingen}
        {--pauze-ms=1000 : Pauze tussen netwerkverzoeken (Nominatim ≤1 req/s)}';

    protected $description = 'Normaliseer ledenlocaties naar postcodegebied + woonplaats (idempotent)';

    /** @var array{leeg: int, kaal_postcode: int, plaatsnaam: int, half_adres: int, twijfel: int, al_ok: int, geschreven: int} */
    private array $counts = [
        'leeg' => 0,
        'kaal_postcode' => 0,
        'plaatsnaam' => 0,
        'half_adres' => 0,
        'twijfel' => 0,
        'al_ok' => 0,
        'geschreven' => 0,
    ];

    /** @var list<array{user_id: int, label: string, land: string, reden: string}> */
    private array $twijfel = [];

    public function handle(LocationGeocoder $geocoder): int
    {
        // Veilig standaard: dry-run, tenzij expliciet --no-dry-run.
        $dryRun = ! $this->option('no-dry-run');
        if ($this->option('dry-run')) {
            $dryRun = true;
        }
        $pauzeMs = max(0, (int) $this->option('pauze-ms'));

        $this->info($dryRun
            ? 'Dry-run: er wordt niets opgeslagen.'
            : 'Schrijven: locaties worden bijgewerkt.');

        User::query()
            ->select(['id', 'country', 'locatie_label', 'postcode_gebied', 'woonplaats', 'lat', 'lon'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($geocoder, $dryRun, $pauzeMs): void {
                foreach ($users as $user) {
                    $this->verwerk($user, $geocoder, $dryRun, $pauzeMs);
                }
            });

        $this->newLine();
        $this->info('Aantallen:');
        $this->line('  leeg (overslaan): '.$this->counts['leeg']);
        $this->line('  kaal postcode → woonplaats: '.$this->counts['kaal_postcode']);
        $this->line('  plaatsnaam → postcodegebied (reverse): '.$this->counts['plaatsnaam']);
        $this->line('  half adres → inkorten (reverse): '.$this->counts['half_adres']);
        $this->line('  al in orde: '.$this->counts['al_ok']);
        $this->line('  twijfel: '.$this->counts['twijfel']);
        if (! $dryRun) {
            $this->line('  geschreven: '.$this->counts['geschreven']);
        }

        if ($this->twijfel !== []) {
            $this->newLine();
            $this->warn('Twijfellijst (user-id · label · land · reden):');
            foreach ($this->twijfel as $row) {
                $this->line(sprintf(
                    '  #%d · %s · %s · %s',
                    $row['user_id'],
                    $row['label'] !== '' ? $row['label'] : '(leeg)',
                    $row['land'],
                    $row['reden'],
                ));
            }
        }

        Log::info('gp:normaliseer-locaties klaar', [
            'dry_run' => $dryRun,
            'counts' => $this->counts,
            'twijfel_n' => count($this->twijfel),
        ]);

        return self::SUCCESS;
    }

    private function verwerk(User $user, LocationGeocoder $geocoder, bool $dryRun, int $pauzeMs): void
    {
        $land = Country::normalize((string) ($user->country ?? Country::NL));
        $label = trim((string) ($user->locatie_label ?? ''));
        $gebied = trim((string) ($user->postcode_gebied ?? ''));
        $plaats = trim((string) ($user->woonplaats ?? ''));

        if ($label === '' && $gebied === '' && $plaats === '') {
            $this->counts['leeg']++;

            return;
        }

        // Al genormaliseerd en label klopt → overslaan (idempotent).
        if ($gebied !== '' && $plaats !== '' && $label === PostalCode::publicLabel($gebied, $plaats)) {
            $this->counts['al_ok']++;

            return;
        }
        if ($gebied === '' && $plaats !== '' && $label === PostalCode::publicLabel(null, $plaats)
            && ! PostalCode::looksLikePostcode($label, $land)
            && ! PostalCode::looksLikeAddressWithNumber($label)) {
            $this->counts['al_ok']++;

            return;
        }

        // (e) land en postcode kloppen niet: bijv. Belgische PC met land NL of omgekeerd range-check.
        if (preg_match('/^\d{4}$/', $label) || preg_match('/^\d{4}\s/', $label)) {
            $pc4 = PostalCode::gebied($label, $land);
            if ($pc4 !== null && $land === Country::NL && PostalCode::isBelgianPostcode($pc4)) {
                // NL-land met typisch BE-gebied is niet per se fout (NL heeft ook 9000-ish),
                // twijfel alleen bij expliciete mismatch via opgeslagen country vs geocode later.
            }
        }

        // (b) kaal 4-cijferig label
        if (preg_match('/^\d{4}$/', $label)) {
            $this->categorieKaalPostcode($user, $land, $label, $geocoder, $dryRun, $pauzeMs);

            return;
        }

        // (d) half adres met huisnummer
        if (PostalCode::looksLikeAddressWithNumber($label)) {
            $this->categorieReverse($user, $land, $label, 'half_adres', $geocoder, $dryRun, $pauzeMs);

            return;
        }

        // Label «1326 Almere» zonder kolommen → splitsen zonder netwerk indien mogelijk
        if (preg_match('/^(\d{4})\s+(.+)$/u', $label, $m) && ! PostalCode::looksLikeAddressWithNumber($label)) {
            $this->pasToe($user, $m[1], trim($m[2]), PostalCode::publicLabel($m[1], trim($m[2])), $dryRun, 'kaal_postcode');

            return;
        }

        // (c) plaatsnaam (of overig tekstueel label): eerst forward lookup (plaats → pc4),
        // daarna reverse als fallback.
        if ($label !== '' && ! PostalCode::looksLikePostcode($label, $land)) {
            $this->categoriePlaatsnaam($user, $land, $label, $geocoder, $dryRun, $pauzeMs);

            return;
        }

        // Volledige NL-postcode als label (legacy 1234AB / 1234 AB) → gebied + geocode plaats
        if (PostalCode::looksLikePostcode($label, $land)) {
            $this->categorieKaalPostcode($user, $land, $label, $geocoder, $dryRun, $pauzeMs);

            return;
        }

        $this->voegTwijfel($user, $label, $land, 'onbekend_formaat');
    }

    private function categorieKaalPostcode(
        User $user,
        string $land,
        string $label,
        LocationGeocoder $geocoder,
        bool $dryRun,
        int $pauzeMs,
    ): void {
        $gebied = PostalCode::gebied($label, $land);
        if ($gebied === null) {
            $this->voegTwijfel($user, $label, $land, 'ongeldige_postcode');

            return;
        }

        // Land/postcode-twijfel: BE-land met NL-achtige 6-char of omgekeerd niet van toepassing op 4 cijfers.
        // Wel: opgeslagen land BE maar query faalt terwijl NL zou werken → blijft twijfel via geocode-fout.

        try {
            $hit = $geocoder->resolve($gebied, $land, true);
        } catch (Throwable $e) {
            Log::warning('gp:normaliseer-locaties geocode-fout', [
                'land' => $land,
                'invoertype' => 'postcode',
                'fout' => class_basename($e),
            ]);
            $this->voegTwijfel($user, $label, $land, 'geocode_fout');

            return;
        }
        if ($pauzeMs > 0) {
            usleep($pauzeMs * 1000);
        }

        if (! ($hit['ok'] ?? false)) {
            $this->voegTwijfel($user, $label, $land, 'postcode_niet_gevonden');

            return;
        }

        // Gevonden land wijkt af van opgeslagen land → twijfel
        $gevondenLand = Country::normalize((string) ($hit['country'] ?? $land));
        if ($gevondenLand !== $land) {
            $this->voegTwijfel($user, $label, $land, 'land_komt_niet_overeen');

            return;
        }

        $plaats = trim((string) ($hit['woonplaats'] ?? ''));
        if ($plaats === '') {
            $plaats = PostalCode::plaatsFromGeoLabel((string) ($hit['label'] ?? ''), $gebied);
        }
        if ($plaats === '') {
            $this->voegTwijfel($user, $label, $land, 'geen_woonplaats');

            return;
        }

        $nieuwLabel = PostalCode::publicLabel($gebied, $plaats);
        $this->pasToe($user, $gebied, $plaats, $nieuwLabel, $dryRun, 'kaal_postcode');
    }

    private function categoriePlaatsnaam(
        User $user,
        string $land,
        string $label,
        LocationGeocoder $geocoder,
        bool $dryRun,
        int $pauzeMs,
    ): void {
        // Forward: plaatsnaam → postcodegebied + woonplaats (PDOK meest voorkomende PC4).
        try {
            $hit = $geocoder->resolve($label, $land, true);
        } catch (Throwable $e) {
            Log::warning('gp:normaliseer-locaties geocode-fout', [
                'land' => $land,
                'invoertype' => 'plaats',
                'fout' => class_basename($e),
            ]);
            $hit = null;
        }
        if ($pauzeMs > 0) {
            usleep($pauzeMs * 1000);
        }

        if (is_array($hit) && ($hit['ok'] ?? false)) {
            $gebied = $hit['postcode'] ?? null;
            $plaats = trim((string) ($hit['woonplaats'] ?? ''));
            if ($plaats === '') {
                $plaats = PostalCode::plaatsFromGeoLabel((string) ($hit['label'] ?? ''), $label);
            }
            if ($plaats === '') {
                $plaats = mb_substr($label, 0, 80);
            }
            if ($gebied !== null && $plaats !== '') {
                $nieuwLabel = PostalCode::publicLabel($gebied, $plaats);
                $this->pasToe($user, $gebied, $plaats, $nieuwLabel, $dryRun, 'plaatsnaam');

                return;
            }
        }

        // Fallback: reverse vanaf opgeslagen coördinaten.
        $this->categorieReverse($user, $land, $label, 'plaatsnaam', $geocoder, $dryRun, $pauzeMs);
    }

    private function categorieReverse(
        User $user,
        string $land,
        string $label,
        string $categorie,
        LocationGeocoder $geocoder,
        bool $dryRun,
        int $pauzeMs,
    ): void {
        if ($user->lat === null || $user->lon === null) {
            $this->voegTwijfel($user, $label, $land, 'geen_lat_lon');

            return;
        }

        try {
            $hit = $geocoder->reverse((float) $user->lat, (float) $user->lon, $land, true);
        } catch (Throwable $e) {
            Log::warning('gp:normaliseer-locaties reverse-fout', [
                'land' => $land,
                'invoertype' => 'reverse',
                'fout' => class_basename($e),
            ]);
            $this->voegTwijfel($user, $label, $land, 'reverse_fout');

            return;
        }
        if ($pauzeMs > 0) {
            usleep($pauzeMs * 1000);
        }

        if ($hit === null) {
            $this->voegTwijfel($user, $label, $land, 'reverse_mislukt');

            return;
        }

        $gebied = $hit['postcode'] ?? null;
        $plaats = trim((string) ($hit['woonplaats'] ?? ''));
        if ($plaats === '') {
            $plaats = PostalCode::plaatsFromGeoLabel((string) ($hit['label'] ?? ''));
        }

        if ($categorie === 'plaatsnaam') {
            // Plaatsnaam-modus: postcodegebied erbij als reverse die geeft; label met gebied+plaats.
            if ($gebied === null || $plaats === '') {
                $this->voegTwijfel($user, $label, $land, 'reverse_onvolledig');

                return;
            }
            $nieuwLabel = PostalCode::publicLabel($gebied, $plaats);
            $this->pasToe($user, $gebied, $plaats, $nieuwLabel, $dryRun, 'plaatsnaam');

            return;
        }

        // half_adres
        if ($gebied === null || $plaats === '') {
            $this->voegTwijfel($user, $label, $land, 'reverse_onvolledig');

            return;
        }
        $nieuwLabel = PostalCode::publicLabel($gebied, $plaats);
        $this->pasToe($user, $gebied, $plaats, $nieuwLabel, $dryRun, 'half_adres');
    }

    private function pasToe(
        User $user,
        ?string $gebied,
        string $plaats,
        string $nieuwLabel,
        bool $dryRun,
        string $categorie,
    ): void {
        $this->counts[$categorie]++;

        $zelfde = (string) ($user->postcode_gebied ?? '') === (string) ($gebied ?? '')
            && (string) ($user->woonplaats ?? '') === $plaats
            && (string) ($user->locatie_label ?? '') === $nieuwLabel;

        if ($zelfde) {
            $this->counts['al_ok']++;
            // categorie was al geteld — corrigeer
            $this->counts[$categorie]--;

            return;
        }

        if ($dryRun) {
            return;
        }

        try {
            User::query()->whereKey($user->id)->update([
                'postcode_gebied' => $gebied,
                'woonplaats' => mb_substr($plaats, 0, 80),
                'locatie_label' => mb_substr($nieuwLabel, 0, 120),
            ]);
            $this->counts['geschreven']++;
        } catch (Throwable $e) {
            Log::warning('gp:normaliseer-locaties opslaan mislukt', [
                'user_id' => $user->id,
                'fout' => class_basename($e),
            ]);
            $this->voegTwijfel($user, (string) $user->locatie_label, Country::normalize((string) $user->country), 'opslaan_mislukt');
        }
    }

    private function voegTwijfel(User $user, string $label, string $land, string $reden): void
    {
        $this->counts['twijfel']++;
        $this->twijfel[] = [
            'user_id' => (int) $user->id,
            'label' => mb_substr($label, 0, 80),
            'land' => $land,
            'reden' => $reden,
        ];
    }
};
