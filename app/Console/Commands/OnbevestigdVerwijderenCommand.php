<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UnverifiedAccountDeletionService;
use Illuminate\Console\Command;

/**
 * Herinneringen dag 7/14, verwijdermail dag 20, opruimen na 7 dagen.
 * Standaard dry-run: alleen aantallen, geen e-mailadressen, namen of id's.
 * --uitvoeren verstuurt mails en verwijdert accounts waarvan de termijn voorbij is.
 */
class OnbevestigdVerwijderenCommand extends Command
{
    protected $signature = 'gp:onbevestigd-verwijderen
        {--dry-run : Toon alleen aantallen (niets versturen of verwijderen)}
        {--uitvoeren : Verstuur herinneringen en verwijdermails, en verwijder accounts na de termijn}
        {--limit=50 : Max. mails of verwijderingen per stap per run}';

    protected $description = 'Onbevestigde accounts (dag 7/14/20): dry-run (aantallen) of --uitvoeren';

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
            'enabled=%s dry_run=%s grace_days=%d min_age_days=%d'
            .' onbevestigd_actief=%d zonder_aanbod_of_berichten=%d overgeslagen_met_aanbod_of_berichten=%d'
            .' op_dag_7=%d op_dag_14=%d op_dag_20_of_ouder=%d eligible_verwijdering=%d'
            .' eligible_herinnering_dag7=%d eligible_herinnering_dag14=%d'
            .' eligible_verwijdermail=%d notice_in_termijn=%d'
            .' sent_dag7=%d sent_dag14=%d sent=%d skipped=%d failed=%d purged=%d',
            ! empty($r['enabled']) ? 'ja' : 'nee',
            ! empty($r['dry_run']) ? 'ja' : 'nee',
            (int) $r['grace_days'],
            (int) $r['min_age_days'],
            (int) $r['onbevestigd_actief'],
            (int) $r['zonder_aanbod_of_berichten'],
            (int) $r['overgeslagen_met_aanbod_of_berichten'],
            (int) $r['op_dag_7'],
            (int) $r['op_dag_14'],
            (int) $r['op_dag_20_of_ouder'],
            (int) $r['eligible_verwijdering'],
            (int) $r['eligible_herinnering_dag7'],
            (int) $r['eligible_herinnering_dag14'],
            (int) $r['eligible_verwijdermail'],
            (int) $r['notice_in_termijn'],
            (int) $r['sent_dag7'],
            (int) $r['sent_dag14'],
            (int) $r['sent'],
            (int) $r['skipped'],
            (int) $r['failed'],
            (int) $r['purged'],
        );
    }
}
