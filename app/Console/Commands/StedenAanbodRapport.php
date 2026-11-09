<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StadCatalogus;
use Illuminate\Console\Command;

/**
 * Read-only overzicht: per gemeente aanbod + deelplekken, en welke steden
 * boven de drempel zitten. Geen persoonsgegevens.
 */
class StedenAanbodRapport extends Command
{
    protected $signature = 'gp:steden-aanbod
        {--refresh : Wis de catalogus-cache vóór het tellen}
        {--alleen-boven : Toon alleen gemeenten boven de drempel}';

    protected $description = 'Toon per gemeente het publieke aanbod (read-only; geen PII)';

    public function handle(StadCatalogus $catalogus): int
    {
        if ($this->option('refresh')) {
            $catalogus->forgetCache();
            $this->info('Catalogus-cache gewist.');
        }

        $min = $catalogus->threshold();
        $rows = $catalogus->rapport();
        if ($this->option('alleen-boven')) {
            $rows = array_values(array_filter($rows, static fn (array $r) => $r['boven_drempel']));
        }

        $this->info("Drempel: {$min} (aanbieding of deelplek). Gemeenten: ".count($rows));
        $this->newLine();

        $table = [];
        $boven = 0;
        foreach ($rows as $row) {
            if ($row['boven_drempel']) {
                $boven++;
            }
            $table[] = [
                $row['naam'],
                $row['slug'],
                $row['provincie'] !== '' ? $row['provincie'] : '—',
                $row['country'],
                $row['bron'],
                $row['aanbod'],
                $row['deelplekken'],
                $row['totaal'],
                $row['boven_drempel'] ? 'ja' : 'nee',
            ];
        }

        $this->table(
            ['plaats', 'slug', 'provincie', 'land', 'bron', 'aanbod', 'deelplekken', 'totaal', 'boven drempel'],
            $table
        );

        $this->newLine();
        $this->info("Boven drempel: {$boven}");

        return self::SUCCESS;
    }
}
