<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optioneel volledig adres (versleuteld). Nooit openbaar of op de kaart; alleen
 * zichtbaar voor de ander in een gesprek na wederzijds «ja», als adres_delen aan staat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['adres_straat', 'adres_huisnummer', 'adres_postcode', 'adres_plaats'] as $col) {
                if (! Schema::hasColumn('users', $col)) {
                    $table->text($col)->nullable();
                }
            }
            if (! Schema::hasColumn('users', 'adres_delen')) {
                $table->boolean('adres_delen')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['adres_straat', 'adres_huisnummer', 'adres_postcode', 'adres_plaats', 'adres_delen'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
