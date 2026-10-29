<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\MaskPersonalDataProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment(['production', 'staging'])) {
            URL::forceScheme('https');
        }

        $this->pushLogMaskingProcessor();
    }

    private function pushLogMaskingProcessor(): void
    {
        try {
            $logger = Log::getLogger();
            if ($logger instanceof Logger) {
                $logger->pushProcessor(new MaskPersonalDataProcessor);
            }
        } catch (Throwable) {
            // Logging nog niet beschikbaar (zeer vroege bootstrap).
        }
    }
}
