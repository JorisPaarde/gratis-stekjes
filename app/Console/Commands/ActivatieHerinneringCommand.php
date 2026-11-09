<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivatieHerinneringService;
use Illuminate\Console\Command;

/**
 * Activatie-tipmail (max. 1×, ~2 dagen na bevestiging, zonder aanbod).
 * Dry-run toont alleen aantallen — geen e-mailadressen of namen.
 * Versturen: via cron of --send (staging: allowlist verplicht).
 */
class ActivatieHerinneringCommand extends Command
{
    protected $signature = 'gp:activatie-herinnering
        {--dry-run : Toon alleen aantallen (niets versturen)}
        {--send : Verstuur tipmails aan eligible leden}
        {--limit=50 : Max. mails per run}';

    protected $description = 'Activatie-herinnering: dry-run (aantallen) of --send';

    public function handle(ActivatieHerinneringService $service): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! $this->option('send');
        $limit = max(1, (int) $this->option('limit'));

        if ($dryRun) {
            return $this->dryRunCounts($service, $limit);
        }

        $result = $service->run($limit, false);
        $this->line(sprintf(
            'enabled=%s sent=%d skipped=%d failed=%d eligible=%d',
            $result['enabled'] ? 'ja' : 'nee',
            $result['sent'],
            $result['skipped'],
            $result['failed'],
            $result['eligible'],
        ));

        return self::SUCCESS;
    }

    private function dryRunCounts(ActivatieHerinneringService $service, int $limit): int
    {
        // Zelfde gates als cron (enabled + ENABLED_AT + staging-allowlist).
        $result = $service->run($limit, true);

        if (! $result['enabled']) {
            $enabledAtRaw = trim((string) config('stekjes.activation_reminder.enabled_at', ''));
            if (! (bool) config('stekjes.activation_reminder.enabled')) {
                $this->warn('ACTIVATION_REMINDER_ENABLED staat uit.');
            } elseif ($enabledAtRaw === '') {
                $this->warn('ACTIVATION_REMINDER_ENABLED_AT ontbreekt.');
            }
        }

        $this->line(sprintf(
            'enabled=%s sent=0 skipped=0 failed=0 eligible=%d dry_run=ja',
            $result['enabled'] ? 'ja' : 'nee',
            $result['eligible'],
        ));

        return self::SUCCESS;
    }
}
