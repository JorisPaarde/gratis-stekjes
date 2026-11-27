<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verwijdermail voor onbevestigde accounts (0.88.0): tijdstip van de
 * waarschuwing, zodat na de termijn (standaard 7 dagen) opgeruimd kan worden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'unverified_deletion_notice_sent_at')) {
                $table->timestamp('unverified_deletion_notice_sent_at')->nullable()->after('activatie_herinnering_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'unverified_deletion_notice_sent_at')) {
                $table->dropColumn('unverified_deletion_notice_sent_at');
            }
        });
    }
};
