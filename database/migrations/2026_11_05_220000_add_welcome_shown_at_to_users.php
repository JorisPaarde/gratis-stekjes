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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'welcome_shown_at')) {
                $table->timestamp('welcome_shown_at')->nullable()->after('email_verified_at');
            }
        });

        // Bestaande geverifieerde leden: scherm nooit tonen.
        if (Schema::hasColumn('users', 'welcome_shown_at')) {
            DB::table('users')
                ->whereNotNull('email_verified_at')
                ->whereNull('welcome_shown_at')
                ->update([
                    'welcome_shown_at' => DB::raw('COALESCE(email_verified_at, CURRENT_TIMESTAMP)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'welcome_shown_at')) {
                $table->dropColumn('welcome_shown_at');
            }
        });
    }
};
