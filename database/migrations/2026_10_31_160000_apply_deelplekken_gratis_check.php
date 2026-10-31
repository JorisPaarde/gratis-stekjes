<?php

declare(strict_types=1);

use App\Services\DeelplekkenGratisCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Data-only: verberg 14 niet-gratis/twijfel-plekken en corrigeer 2 adressen.
 * Idempotent; geen deletes. Schema ongewijzigd (bestaand veld `visible`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deelplekken')) {
            return;
        }

        app(DeelplekkenGratisCheck::class)->apply();
    }

    public function down(): void
    {
        // Bewust leeg: zichtbaarheid/adressen niet automatisch terugzetten.
    }
};
