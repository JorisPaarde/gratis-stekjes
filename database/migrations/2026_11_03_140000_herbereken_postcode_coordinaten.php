<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 0.58.2: eenmalig (via deploy-migrate) coördinaten van leden met een postcode herberekenen.
 * Nooit de deploy breken: elke fout wordt gelogd en genegeerd.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || app()->runningUnitTests()) {
            return;
        }

        try {
            Artisan::call('gp:herbereken-postcodes');
        } catch (Throwable $e) {
            Log::warning('Migratie herbereken-postcodes overgeslagen', ['fout' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        // Niets terug te draaien.
    }
};
