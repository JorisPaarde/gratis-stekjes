<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aanbod', function (Blueprint $table) {
            // Verwijst naar id in data/plantensoorten.json (geen DB-FK).
            $table->unsignedBigInteger('plantensoort_id')->nullable()->after('tekst');
            $table->index('plantensoort_id');
        });
    }

    public function down(): void
    {
        Schema::table('aanbod', function (Blueprint $table) {
            $table->dropIndex(['plantensoort_id']);
            $table->dropColumn('plantensoort_id');
        });
    }
};
