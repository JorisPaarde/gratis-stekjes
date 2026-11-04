<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MilestoneService;
use App\Support\AdminDashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Command;

/**
 * Lees-only: somt actieve bevestigde leden op die een postcode-herinneringsmail
 * zouden krijgen. Verstuurt niets. Geen e-mails, namen of adressen in de output.
 */
class PostcodeHerinneringLijst extends Command
{
    protected $signature = 'gp:postcode-herinnering-lijst
        {--alle : Ook overzicht van alle leden (postcode ja/nee, lidnummer, oprichters-tier)}';

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

        if ($this->option('alle')) {
            $this->alleLeden();
        }

        return self::SUCCESS;
    }

    /**
     * Alle leden (members() zonder beheerders en stats-lezers). Lidnummer en tier
     * volgen MilestoneService::lidnummer(): rangorde op id binnen dezelfde set.
     */
    private function alleLeden(): void
    {
        $admins = AdminDashboard::allowedEmails();
        $heeftIsAdmin = Schema::hasColumn('users', 'is_admin');

        $rijen = [];
        $tiers = ['10' => 0, '50' => 0, '100' => 0, 'geen' => 0];
        $zonder = [];
        $boven100 = [];
        $nr = 0;

        User::query()
            ->members()
            ->when($admins !== [], fn ($q) => $q->whereNotIn(DB::raw('LOWER(email)'), $admins))
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$rijen, &$tiers, &$zonder, &$boven100, &$nr, $heeftIsAdmin): void {
                foreach ($users as $user) {
                    $nr++;
                    if ($heeftIsAdmin && (bool) $user->is_admin) {
                        // Telt mee in de lidnummer-volgorde (zoals MilestoneService), maar staat niet in de lijst.
                        continue;
                    }
                    $tier = 'geen';
                    foreach (array_keys(MilestoneService::OPRICHTERS) as $grens) {
                        if ($nr <= $grens) {
                            $tier = (string) $grens;
                            break;
                        }
                    }
                    $tiers[$tier]++;
                    $geldig = $user->hasValidPostcode();
                    if (! $geldig) {
                        $zonder[] = (int) $user->id;
                    }
                    if ($nr > 100) {
                        $boven100[] = (int) $user->id;
                    }
                    $rijen[] = sprintf('  #%d · postcode %s · lidnummer %d · tier %s', $user->id, $geldig ? 'ja' : 'nee', $nr, $tier);
                }
            });

        $this->newLine();
        $this->info('Alle leden (zonder beheerders en stats-lezers)');
        $this->line('Leden totaal: '.count($rijen));
        $this->line('Zonder geldige postcode: '.count($zonder));
        $this->line('Tier 10: '.$tiers['10']);
        $this->line('Tier 50: '.$tiers['50']);
        $this->line('Tier 100: '.$tiers['100']);
        $this->line('Tier geen: '.$tiers['geen']);
        $this->line('Lidnummer > 100: '.count($boven100).($boven100 !== [] ? ' ('.implode(', ', $boven100).')' : ''));
        $this->line('Zonder postcode id\'s: '.($zonder !== [] ? implode(', ', $zonder) : '-'));
        $this->newLine();
        foreach ($rijen as $r) {
            $this->line($r);
        }
    }
}
