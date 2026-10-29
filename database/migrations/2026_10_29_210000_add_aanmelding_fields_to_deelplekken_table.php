<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deelplekken', function (Blueprint $table) {
            $table->string('source', 32)->default('seed')->after('visible');
            $table->string('status', 32)->default('approved')->after('source');
            $table->string('beschrijving', 1000)->default('')->after('status');
            $table->string('contact_email', 254)->nullable()->after('beschrijving');
            $table->timestamp('privacy_at')->nullable()->after('contact_email');
            $table->timestamp('reviewed_at')->nullable()->after('privacy_at');

            $table->index(['status', 'visible']);
            $table->index(['source', 'status']);
        });

        // Bestaande (geseede) rijen blijven goedgekeurd en zichtbaar.
        if (Schema::hasTable('deelplekken')) {
            DB::table('deelplekken')->update([
                'source' => 'seed',
                'status' => 'approved',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('deelplekken', function (Blueprint $table) {
            $table->dropIndex(['status', 'visible']);
            $table->dropIndex(['source', 'status']);
            $table->dropColumn([
                'source',
                'status',
                'beschrijving',
                'contact_email',
                'privacy_at',
                'reviewed_at',
            ]);
        });
    }
};
