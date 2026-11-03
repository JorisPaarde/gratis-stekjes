<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PdokGeocoder;
use App\Support\Country;
use App\Support\Geo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 0.58.2: herberekent lat/lon van NL-leden met een postcode als locatie (locatie_label = 4 cijfers)
 * via de gefixte PdokGeocoder (fq=type:postcode). Idempotent: alleen bijwerken als het verschilt.
 * Fout bij één postcode = overslaan en loggen. Aanbod heeft geen eigen coördinaten.
 */
class HerberekenPostcodeCoordinaten extends Command
{
    protected $signature = 'gp:herbereken-postcodes {--pauze-ms=250 : Pauze tussen PDOK-verzoeken}';

    protected $description = 'Herbereken coördinaten van NL-leden met een postcode (PDOK, alleen postcodes)';

    public function handle(PdokGeocoder $pdok): int
    {
        $pauzeMs = max(0, (int) $this->option('pauze-ms'));
        $bijgewerkt = 0;
        $overgeslagen = 0;
        $resultaten = [];

        User::query()
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->select(['id', 'country', 'locatie_label', 'lat', 'lon'])
            ->chunkById(100, function ($users) use ($pdok, $pauzeMs, &$bijgewerkt, &$overgeslagen, &$resultaten): void {
                foreach ($users as $user) {
                    $pc = trim((string) $user->locatie_label);
                    if (Country::normalize((string) ($user->country ?? Country::NL)) !== Country::NL
                        || ! preg_match('/^\d{4}$/', $pc)) {
                        continue;
                    }

                    if (! array_key_exists($pc, $resultaten)) {
                        try {
                            $resultaten[$pc] = $pdok->geocode($pc, true);
                        } catch (Throwable $e) {
                            $resultaten[$pc] = null;
                            Log::warning('gp:herbereken-postcodes: PDOK-fout', ['postcode' => $pc, 'fout' => $e->getMessage()]);
                        }
                        if ($pauzeMs > 0) {
                            usleep($pauzeMs * 1000);
                        }
                    }

                    $hit = $resultaten[$pc];
                    if ($hit === null) {
                        $overgeslagen++;
                        Log::warning('gp:herbereken-postcodes: postcode niet gevonden, overgeslagen', ['postcode' => $pc, 'user_id' => $user->id]);

                        continue;
                    }

                    $lat = Geo::roundCoord((float) $hit['lat']);
                    $lon = Geo::roundCoord((float) $hit['lon']);
                    if (abs((float) $user->lat - $lat) < 0.0005 && abs((float) $user->lon - $lon) < 0.0005) {
                        continue;
                    }

                    try {
                        User::query()->whereKey($user->id)->update(['lat' => $lat, 'lon' => $lon]);
                        $bijgewerkt++;
                    } catch (Throwable $e) {
                        $overgeslagen++;
                        Log::warning('gp:herbereken-postcodes: opslaan mislukt', ['user_id' => $user->id, 'fout' => $e->getMessage()]);
                    }
                }
            });

        Log::info('gp:herbereken-postcodes klaar', ['bijgewerkt' => $bijgewerkt, 'overgeslagen' => $overgeslagen]);
        $this->info("Bijgewerkt: {$bijgewerkt}, overgeslagen: {$overgeslagen}");

        return self::SUCCESS;
    }
}
