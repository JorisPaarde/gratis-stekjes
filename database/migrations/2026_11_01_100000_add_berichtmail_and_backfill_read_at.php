<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leden kunnen de mail bij een nieuw bericht uitzetten (standaard aan).
 * read_at werd tot nu toe niet bijgehouden: bestaande berichten gelden als gelezen,
 * zodat de nieuwe teller voor ongelezen berichten niet met oude berichten start.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'berichtmail')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('berichtmail')->default(true);
            });
        }

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'read_at')) {
            DB::table('messages')->whereNull('read_at')->update(['read_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'berichtmail')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('berichtmail');
            });
        }
    }
};
