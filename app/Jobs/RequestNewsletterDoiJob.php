<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\BrevoNewsletterService;
use App\Support\SafeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brevo double opt-in na eigen e-mailbevestiging. Fail-soft + retries via queue.
 */
class RequestNewsletterDoiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $userId) {}

    public function handle(BrevoNewsletterService $brevo): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null) {
            return;
        }

        $result = $brevo->sendDoubleOptIn($user);
        if (! ($result['ok'] ?? false) && ! ($result['skipped'] ?? false)) {
            throw new \RuntimeException('Brevo DOI mislukt (status '.((int) ($result['status'] ?? 0)).')');
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('brevo.doi_job_failed', [
            'user_id' => $this->userId,
            'error' => $e ? SafeMail::safeSummary($e) : 'unknown',
        ]);
    }
}
