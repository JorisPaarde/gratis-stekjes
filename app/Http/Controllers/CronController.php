<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AanbodExpiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class CronController extends Controller
{
    public function __invoke(Request $request, AanbodExpiryService $expiry): Response|JsonResponse
    {
        $expected = (string) config('stekjes.cron_token', '');
        $given = (string) $request->header('X-Cron-Token', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response('Forbidden', 403)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        try {
            $queueExit = Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => 45,
                '--tries' => 3,
            ]);

            $jobs = [
                'queue_work' => ['exit' => $queueExit],
                'expiry_reminders' => $expiry->sendExpiryReminders(),
                'purge_geocode_cache' => ['deleted' => $expiry->purgeGeocodeCache()],
                'purge_mail_log' => ['deleted' => $expiry->purgeMailLog()],
            ];
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e::class,
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'version' => config('site.version'),
            'jobs' => $jobs,
            'at' => now()->toIso8601String(),
        ]);
    }
}
