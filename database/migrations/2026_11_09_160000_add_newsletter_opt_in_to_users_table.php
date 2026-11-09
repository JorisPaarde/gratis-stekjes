<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'newsletter_opt_in')) {
                $table->boolean('newsletter_opt_in')->default(false);
            }
            if (! Schema::hasColumn('users', 'newsletter_opt_in_at')) {
                $table->timestamp('newsletter_opt_in_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'newsletter_doi_sent_at')) {
                $table->timestamp('newsletter_doi_sent_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'newsletter_listed_at')) {
                // Laatst gezien op de Brevo-lijst (webhook/sync); voorbereiding badge 0.79.4.
                $table->timestamp('newsletter_listed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['newsletter_opt_in', 'newsletter_opt_in_at', 'newsletter_doi_sent_at', 'newsletter_listed_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
