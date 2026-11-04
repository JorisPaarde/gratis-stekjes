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
            if (! Schema::hasColumn('evenementen', 'organisatie_slug')) {
                $table->string('organisatie_slug', 80)->nullable()->after('organisator');
                $table->index('organisatie_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evenementen', function (Blueprint $table) {
            if (Schema::hasColumn('evenementen', 'organisatie_slug')) {
                $table->dropIndex(['organisatie_slug']);
                $table->dropColumn('organisatie_slug');
            }
        });
    }
};
