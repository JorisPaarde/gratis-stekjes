<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evenementen', function (Blueprint $table) {
            if (! Schema::hasColumn('evenementen', 'start_tijd')) {
                $table->time('start_tijd')->nullable()->after('eind_datum');
            }
            if (! Schema::hasColumn('evenementen', 'eind_tijd')) {
                $table->time('eind_tijd')->nullable()->after('start_tijd');
            }
            if (! Schema::hasColumn('evenementen', 'adres')) {
                $table->string('adres', 255)->default('')->after('plaats');
            }
            if (! Schema::hasColumn('evenementen', 'omschrijving')) {
                $table->string('omschrijving', 500)->default('')->after('organisator');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evenementen', function (Blueprint $table) {
            foreach (['omschrijving', 'adres', 'eind_tijd', 'start_tijd'] as $col) {
                if (Schema::hasColumn('evenementen', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
