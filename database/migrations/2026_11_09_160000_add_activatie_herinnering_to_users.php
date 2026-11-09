<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activatie-herinnering (0.79.2): max. 1× mail ~2 dagen na bevestiging
 * als er nog geen aanbod is. Opt-out via activatieherinnering.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'activatieherinnering')) {
                $table->boolean('activatieherinnering')->default(true)->after('berichtmail');
            }
            if (! Schema::hasColumn('users', 'activatie_herinnering_sent_at')) {
                $table->timestamp('activatie_herinnering_sent_at')->nullable()->after('verification_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['activatie_herinnering_sent_at', 'activatieherinnering'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
