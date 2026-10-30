<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->string('query_key', 190)->primary();
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->string('label', 255);
            $table->timestamp('cached_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');
    }
};
