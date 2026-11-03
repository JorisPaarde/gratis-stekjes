<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stats-lezer-vlag: alleen /beheer/statistieken, geen volledige beheerder. Standaard uit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_stats_viewer')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_stats_viewer')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_stats_viewer')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('is_stats_viewer');
            });
        }
    }
};
