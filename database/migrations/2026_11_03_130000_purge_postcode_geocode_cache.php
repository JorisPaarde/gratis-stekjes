<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 0.58.1: postcode-zoekopdrachten (2037, 2037 KB) gaven via PDOK soms een woonplaats met
 * dezelfde code (2037 = Nederweert). Die foute cache-regels weg, zodat ze opnieuw
 * (alleen als postcode) worden opgezocht. Alleen cache; geen gebruikersdata.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('geocode_cache')) {
            return;
        }

        $weg = [];
        foreach (DB::table('geocode_cache')->select('query_key')->cursor() as $row) {
            $key = (string) $row->query_key;
            if (preg_match('/^(?:nl:|nl,be:)?\d{4}\s*(?:[a-z]{2})?$/i', $key)) {
                $weg[] = $key;
            }
        }

        foreach (array_chunk($weg, 200) as $chunk) {
            DB::table('geocode_cache')->whereIn('query_key', $chunk)->delete();
        }
    }

    public function down(): void
    {
        // Cache vult zichzelf weer.
    }
};
