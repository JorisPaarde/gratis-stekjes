<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Initiatiefpagina's staan sinds 0.72.0 in data/organisaties.json («initiatief»).
 * De tabel wordt niet meer gelezen; opruimen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organisatie_initiatieven');
    }

    public function down(): void
    {
        // Geen terugweg: inhoud staat in data/organisaties.json.
    }
};
