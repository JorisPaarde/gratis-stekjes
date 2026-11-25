<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NewsletterOptOutBackfill;
use Illuminate\Console\Command;

class NieuwsbriefBackfillCommand extends Command
{
    protected $signature = 'gp:nieuwsbrief-backfill {--dry-run : Alleen het aantal tonen, niets wijzigen}';

    protected $description = 'Zet bevestigde, actieve leden zonder keuze op nieuwsbrief aan (opt-out); idempotent. Verstuurt niets.';

    public function handle(NewsletterOptOutBackfill $backfill): int
    {
        if ($this->option('dry-run')) {
            $this->info('Zou aanzetten: '.$backfill->count().' bevestigde leden (dry-run, niets gewijzigd).');

            return self::SUCCESS;
        }

        $this->info('Aangezet: '.$backfill->apply().' bevestigde leden.');

        return self::SUCCESS;
    }
}
