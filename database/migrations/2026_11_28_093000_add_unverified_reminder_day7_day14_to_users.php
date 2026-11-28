<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Herinneringen op dag 7 en dag 14 voor onbevestigde accounts (0.90.0).
 * Elke herinnering hoogstens één keer (eigen tijdstipkolom).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'verification_reminder_day7_sent_at')) {
                $table->timestamp('verification_reminder_day7_sent_at')->nullable()->after('verification_reminder_sent_at');
            }
            if (! Schema::hasColumn('users', 'verification_reminder_day14_sent_at')) {
                $table->timestamp('verification_reminder_day14_sent_at')->nullable()->after('verification_reminder_day7_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['verification_reminder_day14_sent_at', 'verification_reminder_day7_sent_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
