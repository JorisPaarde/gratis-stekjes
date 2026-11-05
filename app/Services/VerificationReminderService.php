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
 *
 * Staging: REMINDER_TEST_RECIPIENTS is verplicht (fail-closed bij lege allowlist).
 * Versturen gebeurt pas ná atomische claim op verification_reminder_sent_at.
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

        $allowlist = $this->normalizedAllowlist();

        // Staging fail-closed: zonder geldige allowlist nooit alle accounts mailen.
        if (app()->environment('staging') && $allowlist === []) {
            Log::warning('verification_reminder staging zonder REMINDER_TEST_RECIPIENTS; niets verstuurd');

            return ['enabled' => true, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'eligible' => 0];
        }

        $afterHours = max(1, (int) config('stekjes.verification_reminder.after_hours', 24));
        $cutoff = now()->subHours($afterHours);

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
            // Atomair claimen vóór versturen: overlappende cronruns → max 1 mail.
            if (! $this->claimReminderSlot((int) $user->id)) {
                $skipped++;

                continue;
            }

            try {
                $user->refresh();
                $user->rotateEmailVerificationToken();
                $ok = SafeMail::notify($user, new VerifyEmailReminderDutch);
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

    /**
     * Claim het herinnerings-slot atomisch (compare-and-set).
     * true = deze worker mag versturen; false = andere worker won of niet meer eligible.
     */
    public function claimReminderSlot(int $userId): bool
    {
        if ($userId < 1 || ! Schema::hasColumn('users', 'verification_reminder_sent_at')) {
            return false;
        }

        $affected = User::query()
            ->whereKey($userId)
            ->where('active', true)
            ->whereNull('email_verified_at')
            ->whereNull('verification_reminder_sent_at')
            ->update(['verification_reminder_sent_at' => now()]);

        return $affected === 1;
    }

    /**
     * @return list<string>
     */
    private function normalizedAllowlist(): array
    {
        $raw = array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            (array) config('stekjes.verification_reminder.test_recipients', [])
        );

        return array_values(array_filter(
            $raw,
            static fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
