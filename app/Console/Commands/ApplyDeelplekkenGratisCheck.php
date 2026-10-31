<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeelplekkenGratisCheck;
use Illuminate\Console\Command;

/**
 * Past de gratis-check (okt 2026) toe: 14 verbergen + 2 adressen.
 * Idempotent; verwijdert niets. Draait ook via migratie bij deploy.
 */
class ApplyDeelplekkenGratisCheck extends Command
{
    protected $signature = 'deelplekken:apply-gratis-check';

    protected $description = 'Verberg niet-gratis/twijfel-deelplekken en pas 2 adrescorrecties toe (alleen die 16 rijen)';

    public function handle(DeelplekkenGratisCheck $check): int
    {
        $result = $check->apply();

        $this->info('hidden='.$result['hidden'].' corrected='.$result['corrected']);
        if ($result['missing'] !== []) {
            $this->warn('missing='.implode(',', $result['missing']));
        }

        return self::SUCCESS;
    }
}
