<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotente goedkeuringsmail: één keer mailen bij eerste approve.
 * Bestaande approved-rijen krijgen nu() zodat er geen backfill-mails gaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verenigingen') && ! Schema::hasColumn('verenigingen', 'approval_mailed_at')) {
            Schema::table('verenigingen', function (Blueprint $table): void {
                $table->timestamp('approval_mailed_at')->nullable()->after('created_by');
            });

            DB::table('verenigingen')
                ->where('status', 'approved')
                ->whereNull('approval_mailed_at')
                ->update(['approval_mailed_at' => now()]);
        }

        if (Schema::hasTable('deelplekken') && ! Schema::hasColumn('deelplekken', 'approval_mailed_at')) {
            Schema::table('deelplekken', function (Blueprint $table): void {
                $table->timestamp('approval_mailed_at')->nullable()->after('reviewed_at');
            });

            DB::table('deelplekken')
                ->where('status', 'approved')
                ->whereNull('approval_mailed_at')
                ->update(['approval_mailed_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('verenigingen') && Schema::hasColumn('verenigingen', 'approval_mailed_at')) {
            Schema::table('verenigingen', function (Blueprint $table): void {
                $table->dropColumn('approval_mailed_at');
            });
        }

        if (Schema::hasTable('deelplekken') && Schema::hasColumn('deelplekken', 'approval_mailed_at')) {
            Schema::table('deelplekken', function (Blueprint $table): void {
                $table->dropColumn('approval_mailed_at');
            });
        }
    }
};
