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
            if (! Schema::hasColumn('evenementen', 'lat')) {
                $table->decimal('lat', 9, 6)->nullable()->after('plaats');
            }
            if (! Schema::hasColumn('evenementen', 'lon')) {
                $table->decimal('lon', 9, 6)->nullable()->after('lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evenementen', function (Blueprint $table) {
            if (Schema::hasColumn('evenementen', 'lon')) {
                $table->dropColumn('lon');
            }
            if (Schema::hasColumn('evenementen', 'lat')) {
                $table->dropColumn('lat');
            }
        });
    }
};
