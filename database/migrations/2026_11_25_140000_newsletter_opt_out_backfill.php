<?php

declare(strict_types=1);

use App\Services\NewsletterOptOutBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Nieuwsbrief wordt opt-out: kolom voor "zelf afgemeld" + eenmalige zet van bevestigde,
 * actieve leden op newsletter_opt_in = true. Onbevestigde accounts niet. Geen Brevo-call.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'newsletter_opt_out_at')) {
                // Moment waarop het lid zich afmeldde (profiel of Brevo). Voorkomt her-aanzetten door backfill.
                $table->timestamp('newsletter_opt_out_at')->nullable();
            }
            if (Schema::hasColumn('users', 'newsletter_doi_sent_at')) {
                $table->dropColumn('newsletter_doi_sent_at');
            }
        });

        $count = (new NewsletterOptOutBackfill())->apply();
        Log::info('newsletter.optout_backfill', ['members_enabled' => $count]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'newsletter_opt_out_at')) {
                $table->dropColumn('newsletter_opt_out_at');
            }
        });
    }
};
