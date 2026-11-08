<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Aanbod;
use App\Support\AanbodTekst;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only audit: aanbod met lege/onbruikbare plantnaam.
 * Nooit writes. Geen e-mail/telefoon/straat in de uitvoer — alleen id’s en redenen.
 */
class AuditLegeAanbodTekst extends Command
{
    protected $signature = 'gp:audit-lege-aanbod-tekst
        {--only-public : Alleen zichtbaar + niet verlopen (publieke lijstfilters zonder lid-check)}
        {--json : Machineleesbare JSON-uitvoer}';

    protected $description = 'Read-only: tel en toon aanbod-ids met lege/onbruikbare plantnaam (geen writes, geen PII)';

    public function handle(): int
    {
        if (! Schema::hasTable('aanbod')) {
            $this->error('Tabel aanbod ontbreekt.');

            return self::FAILURE;
        }

        $onlyPublic = (bool) $this->option('only-public');
        $asJson = (bool) $this->option('json');

        $query = Aanbod::query()->orderBy('id');
        if ($onlyPublic) {
            $query->publiclyListed();
        }

        $hits = [];
        $counts = [
            'leeg' => 0,
            'alleen_whitespace' => 0,
            'totaal_onbruikbaar' => 0,
            'met_foto' => 0,
            'zonder_foto' => 0,
            'scan' => 0,
        ];

        $query->withCount('fotos')->chunkById(200, function ($rows) use (&$hits, &$counts): void {
            foreach ($rows as $aanbod) {
                $counts['scan']++;
                $raw = (string) ($aanbod->tekst ?? '');
                $usable = AanbodTekst::isUsable($raw);
                if ($usable) {
                    continue;
                }

                $reden = $raw === '' ? 'leeg' : 'alleen_whitespace';
                $counts[$reden]++;
                $counts['totaal_onbruikbaar']++;
                $hasFoto = ((int) $aanbod->fotos_count) > 0;
                if ($hasFoto) {
                    $counts['met_foto']++;
                } else {
                    $counts['zonder_foto']++;
                }

                $hits[] = [
                    'id' => (int) $aanbod->id,
                    'type' => (string) $aanbod->type,
                    'aantal' => (int) ($aanbod->aantal ?? 1),
                    'reden' => $reden,
                    'has_fotos' => $hasFoto,
                    'visible' => (bool) $aanbod->visible,
                    'expires_at' => $aanbod->expires_at?->toIso8601String(),
                ];
            }
        });

        if ($asJson) {
            $this->line(json_encode([
                'dry_run' => true,
                'only_public' => $onlyPublic,
                'counts' => $counts,
                'items' => $hits,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Read-only audit (geen writes).'.($onlyPublic ? ' Filter: publiclyListed.' : ''));
        $this->line('  gescand: '.$counts['scan']);
        $this->line('  onbruikbaar: '.$counts['totaal_onbruikbaar']);
        $this->line('  · leeg: '.$counts['leeg']);
        $this->line('  · alleen whitespace: '.$counts['alleen_whitespace']);
        $this->line('  · met foto: '.$counts['met_foto']);
        $this->line('  · zonder foto: '.$counts['zonder_foto']);

        if ($hits !== []) {
            $this->newLine();
            $this->line('Ids (id · type · reden · foto):');
            foreach ($hits as $row) {
                $this->line(sprintf(
                    '  #%d · %s · %s · %s',
                    $row['id'],
                    $row['type'],
                    $row['reden'],
                    $row['has_fotos'] ? 'foto' : 'geen-foto'
                ));
            }
        }

        return self::SUCCESS;
    }
}
