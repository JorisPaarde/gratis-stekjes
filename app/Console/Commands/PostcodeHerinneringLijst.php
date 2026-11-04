<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Lees-only: somt actieve bevestigde leden op die een postcode-herinneringsmail
 * zouden krijgen. Verstuurt niets. Geen e-mails, namen of adressen in de output.
 */
class PostcodeHerinneringLijst extends Command
{
    protected $signature = 'gp:postcode-herinnering-lijst';

    protected $description = 'Som leden op zonder geldige postcode (alleen lezen, stuurt geen mail)';

    public function handle(): int
    {
        $counts = [
            'leeg' => 0,
            'ongeldig_formaat' => 0,
            'geen_plaats' => 0,
        ];
        /** @var list<int> $ids */
        $ids = [];

        User::query()
            ->members()
            ->where('active', true)
            ->whereNotNull('email_verified_at')
            ->select(['id', 'country', 'locatie_label', 'postcode_gebied', 'woonplaats', 'lat', 'lon'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$counts, &$ids): void {
                foreach ($users as $user) {
                    $reden = $user->postcodeValidityReason();
                    if ($reden === null) {
                        continue;
                    }
                    $counts[$reden] = ($counts[$reden] ?? 0) + 1;
                    $ids[] = (int) $user->id;
                }
            });

        $totaal = count($ids);

        $this->info('Postcode-herinnering (lees-only, geen mail verstuurd)');
        $this->line('Totaal: '.$totaal);
        $this->line('  leeg: '.$counts['leeg']);
        $this->line('  ongeldig_formaat: '.$counts['ongeldig_formaat']);
        $this->line('  geen_plaats: '.$counts['geen_plaats']);

        if ($ids !== []) {
            $this->newLine();
            $this->line('Lid-id\'s: '.implode(', ', $ids));
        }

        return self::SUCCESS;
    }
}
