<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eenmalige overgang naar nieuwsbrief-opt-out: BEVESTIGDE, actieve leden die nog nooit een
 * keuze maakten krijgen newsletter_opt_in = true. Onbevestigde accounts blijven uit.
 * Idempotent: wie zelf uit (newsletter_opt_out_at) of al aan stond, wordt niet aangeraakt.
 * Verstuurt niets en roept Brevo niet aan; de sync zet de leden pas daarna op de lijst.
 */
final class NewsletterOptOutBackfill
{
    private function candidates(): Builder
    {
        return DB::table('users')
            ->whereNotNull('email_verified_at')
            ->where('active', true)
            ->where('newsletter_opt_in', false)
            ->whereNull('newsletter_opt_in_at')
            ->whereNull('newsletter_opt_out_at');
    }

    private function ready(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', 'newsletter_opt_in')
            && Schema::hasColumn('users', 'newsletter_opt_out_at');
    }

    /** Aantal leden dat nu zou worden aangezet (alleen een getal). */
    public function count(): int
    {
        return $this->ready() ? (int) $this->candidates()->count() : 0;
    }

    /** Zet de vlag aan; geeft het aantal aangezette leden terug. */
    public function apply(): int
    {
        if (! $this->ready()) {
            return 0;
        }

        return (int) $this->candidates()->update([
            'newsletter_opt_in' => true,
            'newsletter_opt_in_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
