<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailReminderDutch;
use App\Support\SafeMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Eén herinnering na 24 uur voor onbevestigde accounts, achter config-vlag.
 * Alleen accounts aangemaakt ná VERIFICATION_REMINDER_ENABLED_AT.
 */
final class VerificationReminderService
{
    /**
     * @return array{enabled: bool, sent: int, skipped: int, failed: int, eligible: int}
     */
    public function run(int $limit = 50): array
    {
        $empty = ['enabled' => false, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'eligible' => 0];

        if (! (bool) config('stekjes.verification_reminder.enabled')) {
            return $empty;
        }

        if (! Schema::hasColumn('users', 'verification_reminder_sent_at')) {
            return $empty;
        }

        $enabledAtRaw = trim((string) config('stekjes.verification_reminder.enabled_at', ''));
        if ($enabledAtRaw === '') {
            Log::warning('verification_reminder enabled zonder ENABLED_AT; niets verstuurd');

            return $empty;
        }

        try {
            $enabledAt = CarbonImmutable::parse($enabledAtRaw);
        } catch (Throwable) {
            Log::warning('verification_reminder ENABLED_AT ongeldig; niets verstuurd');

            return $empty;
        }

        $afterHours = max(1, (int) config('stekjes.verification_reminder.after_hours', 24));
        $cutoff = now()->subHours($afterHours);
        $allowlist = array_values(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            (array) config('stekjes.verification_reminder.test_recipients', [])
        )));

        $query = User::query()
            ->members()
            ->where('active', true)
            ->whereNull('email_verified_at')
            ->whereNull('verification_reminder_sent_at')
            ->where('created_at', '>=', $enabledAt)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id');

        if ($allowlist !== []) {
            $query->whereIn('email', $allowlist);
        }

        $eligible = (clone $query)->count();
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($query->limit($limit)->get() as $user) {
            try {
                $user->rotateEmailVerificationToken();
                $ok = SafeMail::notify($user, new VerifyEmailReminderDutch);
                // Markeer als verstuurd ook bij SMTP-fout: voorkomt mailstorm; status zit in mail_log.
                $user->forceFill(['verification_reminder_sent_at' => now()])->save();
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('verification_reminder mislukt: '.$e::class);
            }
        }

        return [
            'enabled' => true,
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'eligible' => $eligible,
        ];
    }
}
