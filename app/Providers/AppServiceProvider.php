<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\MaskPersonalDataProcessor;
use App\Models\User;
use App\Services\MessagingService;
use App\Services\VolgendeStap;
use App\Support\AdminDashboard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '') {
            // Canonical/route()/asset-URL's altijd uit APP_URL, niet uit de request-host (www-lek).
            URL::forceRootUrl($appUrl);
        }

        if ($this->app->environment(['production', 'staging'])) {
            URL::forceScheme('https');
        }

        $this->pushLogMaskingProcessor();

        Gate::define('see-admin-dashboard', function (?User $user): bool {
            if (request()->hasSession() && session('admin_ok')) {
                return true;
            }

            return AdminDashboard::userMayAccess($user);
        });

        // Teller voor ongelezen berichten in de navigatie (alleen ingelogd; fouten breken de pagina niet).
        View::composer(['layouts.site', 'account.index'], function ($view): void {
            $count = 0;
            try {
                $user = auth()->user();
                if ($user !== null) {
                    $count = app(MessagingService::class)->unreadCount((int) $user->id);
                }
            } catch (Throwable) {
                $count = 0;
            }
            $view->with('ongelezenBerichten', $count);
        });

        View::composer('partials.volgende-stap', function ($view): void {
            $stap = null;
            try {
                $user = auth()->user();
                if ($user !== null) {
                    $stap = app(VolgendeStap::class)->voor($user);
                }
            } catch (Throwable) {
                $stap = null;
            }
            $view->with('volgendeStap', $stap);
        });

        RateLimiter::for('plantensoorten-zoek', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?? 'unknown');
        });
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
