<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aantal is voortaan verplicht: NULL → 1, daarna NOT NULL DEFAULT 1.
 * MySQL/MariaDB (cPanel): MODIFY. SQLite (tests): Schema::change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aanbod') || ! Schema::hasColumn('aanbod', 'aantal')) {
            return;
        }

        DB::table('aanbod')->whereNull('aantal')->update(['aantal' => 1]);

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Veilig op cPanel: eerst backfill, dan in één ALTER NOT NULL + DEFAULT.
            DB::statement('ALTER TABLE `aanbod` MODIFY `aantal` SMALLINT UNSIGNED NOT NULL DEFAULT 1');

            return;
        }

        Schema::table('aanbod', function (Blueprint $table): void {
            $table->unsignedSmallInteger('aantal')->default(1)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('aanbod') || ! Schema::hasColumn('aanbod', 'aantal')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `aanbod` MODIFY `aantal` SMALLINT UNSIGNED NULL');

            return;
        }

        Schema::table('aanbod', function (Blueprint $table): void {
            $table->unsignedSmallInteger('aantal')->nullable()->default(null)->change();
        });
    }
};
