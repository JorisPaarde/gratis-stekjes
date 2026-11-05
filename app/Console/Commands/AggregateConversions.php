<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ConversionTracker;
use Illuminate\Console\Command;

class AggregateConversions extends Command
{
    protected $signature = 'gp:conversion-aggregate';

    protected $description = 'Aggregeer conversion_events naar conversion_daily en wis ruwe events ouder dan retentie';

    public function handle(ConversionTracker $tracker): int
    {
        $result = $tracker->aggregateAndPurge();
        $this->info(sprintf(
            'aggregated_days=%d deleted_events=%d cleared_flows=%d nulled_event_flows=%d',
            $result['aggregated_days'],
            $result['deleted_events'],
            $result['cleared_flows'],
            $result['nulled_event_flows'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
