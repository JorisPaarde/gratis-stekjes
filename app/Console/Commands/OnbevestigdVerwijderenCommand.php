<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UnverifiedAccountDeletionService;
use Illuminate\Console\Command;

/**
 * Verwijdermail + opruimen van onbevestigde accounts.
 * Standaard dry-run: alleen aantallen, geen e-mailadressen, namen of id's.
 * --uitvoeren verstuurt mails en verwijdert accounts waarvan de termijn voorbij is.
 */
class OnbevestigdVerwijderenCommand extends Command
{
    protected $signature = 'gp:onbevestigd-verwijderen
        {--dry-run : Toon alleen aantallen (niets versturen of verwijderen)}
        {--uitvoeren : Verstuur verwijdermails en verwijder accounts na de termijn}
        {--limit=50 : Max. mails of verwijderingen per run}';

    protected $description = 'Onbevestigde accounts: dry-run (aantallen) of --uitvoeren';

    public function handle(UnverifiedAccountDeletionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! $this->option('uitvoeren');
        $limit = max(1, (int) $this->option('limit'));

        $result = $service->run($dryRun, $limit);

        if ($dryRun && ! $result['enabled']) {
            $this->warn('UNVERIFIED_DELETION_ENABLED staat uit (tellen mag; versturen/verwijderen niet).');
        }

        $this->line($this->formatCounts($result));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function formatCounts(array $r): string
    {
        return sprintf(
            'enabled=%s dry_run=%s grace_days=%d min_age_days=%d min_reminders=%d'
            .' onbevestigd_actief=%d onbevestigd_min_leeftijd=%d'
            .' herinneringen_0=%d herinneringen_1=%d herinneringen_2=%d herinneringen_3plus=%d'
            .' met_auto_herinnering=%d eligible_verwijdermail=%d notice_in_termijn=%d eligible_verwijdering=%d'
            .' met_aanbod=%d met_gesprekken=%d met_ervaringen=%d met_vereniging=%d'
            .' sent=%d skipped=%d failed=%d purged=%d',
            ! empty($r['enabled']) ? 'ja' : 'nee',
            ! empty($r['dry_run']) ? 'ja' : 'nee',
            (int) $r['grace_days'],
            (int) $r['min_age_days'],
            (int) $r['min_reminders'],
            (int) $r['onbevestigd_actief'],
            (int) $r['onbevestigd_min_leeftijd'],
            (int) $r['herinneringen_0'],
            (int) $r['herinneringen_1'],
            (int) $r['herinneringen_2'],
            (int) $r['herinneringen_3plus'],
            (int) $r['met_auto_herinnering'],
            (int) $r['eligible_verwijdermail'],
            (int) $r['notice_in_termijn'],
            (int) $r['eligible_verwijdering'],
            (int) $r['met_aanbod'],
            (int) $r['met_gesprekken'],
            (int) $r['met_ervaringen'],
            (int) $r['met_vereniging'],
            (int) $r['sent'],
            (int) $r['skipped'],
            (int) $r['failed'],
            (int) $r['purged'],
        );
    }
}
