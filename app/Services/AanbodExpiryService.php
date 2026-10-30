<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AanbodExpiryReminderMail;
use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\MailLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AanbodExpiryService
{
    /**
     * @return array{checked: int, sent: int, skipped: int, failed: int}
     */
    public function sendExpiryReminders(): array
    {
        $stats = ['checked' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        $reminderDays = (int) config('stekjes.aanbod_reminder_days', 7);
        $tokenDays = (int) config('stekjes.aanbod_renew_token_days', 14);

        $rows = Aanbod::query()
            ->with('user')
            ->where('visible', true)
            ->whereNull('expiry_reminder_sent_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($reminderDays))
            ->whereHas('user', fn ($q) => $q->where('active', true)->whereNotNull('email_verified_at'))
            ->orderBy('id')
            ->get();

        $stats['checked'] = $rows->count();

        foreach ($rows as $aanbod) {
            $token = $this->makeRenewToken();
            $claimed = Aanbod::query()
                ->whereKey($aanbod->id)
                ->whereNull('expiry_reminder_sent_at')
                ->update([
                    'expiry_reminder_sent_at' => now(),
                    'renew_token' => $token,
                    'renew_token_expires' => now()->addDays($tokenDays),
                ]);

            if ($claimed === 0) {
                $stats['skipped']++;

                continue;
            }

            $email = (string) ($aanbod->user?->email ?? '');
            if ($email === '') {
                $this->clearReminderClaim($aanbod->id, $token);
                $stats['failed']++;

                continue;
            }

            $preview = mb_substr(trim((string) $aanbod->tekst), 0, 80);
            $url = route('account.aanbod-verlengen', ['token' => $token]);

            try {
                Mail::to($email)->send(new AanbodExpiryReminderMail($url, $preview));
                $stats['sent']++;
            } catch (Throwable) {
                $this->clearReminderClaim($aanbod->id, $token);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * @return array{ok: bool, error?: string, expires_at?: string}
     */
    public function renewByToken(string $token): array
    {
        $token = trim($token);
        if ($token === '' || ! preg_match('/^[a-f0-9]{16,96}\.[a-f0-9]{16}$/', $token)) {
            return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
        }
        if (! $this->verifyRenewTokenSignature($token)) {
            return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
        }

        $aanbod = Aanbod::query()->where('renew_token', $token)->first();
        if (! $aanbod) {
            return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
        }
        if ($aanbod->renew_token_expires === null || $aanbod->renew_token_expires->isPast()) {
            return ['ok' => false, 'error' => 'Deze verlenglink is verlopen.'];
        }

        $claimed = Aanbod::query()
            ->whereKey($aanbod->id)
            ->where('renew_token', $token)
            ->update([
                'renew_token' => null,
                'renew_token_expires' => null,
            ]);
        if ($claimed === 0) {
            return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
        }

        $ttl = (int) config('stekjes.aanbod_ttl_days', 60);
        $expires = now()->addDays($ttl);
        $aanbod->refresh();
        $aanbod->update([
            'expires_at' => $expires,
            'expiry_reminder_sent_at' => null,
            'visible' => true,
        ]);

        return ['ok' => true, 'expires_at' => $expires->toDateTimeString()];
    }

    public function purgeGeocodeCache(): int
    {
        $days = (int) config('stekjes.geocode_cache_retention_days', 90);

        return GeocodeCache::query()
            ->where('cached_at', '<', now()->subDays($days))
            ->delete();
    }

    public function purgeMailLog(): int
    {
        if (! Schema::hasTable('mail_log')) {
            return 0;
        }

        $days = (int) config('stekjes.mail_log_retention_days', 90);

        return MailLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    public function makeRenewToken(): string
    {
        $raw = bin2hex(random_bytes(12));
        $sig = substr(hash_hmac('sha256', $raw, $this->signingKey()), 0, 16);

        return $raw.'.'.$sig;
    }

    public function verifyRenewTokenSignature(string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$raw, $sig] = $parts;
        if ($raw === '' || $sig === '' || ! ctype_xdigit($raw) || ! ctype_xdigit($sig)) {
            return false;
        }
        $expected = substr(hash_hmac('sha256', $raw, $this->signingKey()), 0, 16);

        return hash_equals($expected, $sig);
    }

    private function signingKey(): string
    {
        return hash('sha256', implode('|', [
            (string) config('stekjes.cron_token', ''),
            (string) config('app.key', ''),
            (string) config('app.url', ''),
        ]));
    }

    private function clearReminderClaim(int $id, string $token): void
    {
        DB::table('aanbod')
            ->where('id', $id)
            ->where('renew_token', $token)
            ->update([
                'expiry_reminder_sent_at' => null,
                'renew_token' => null,
                'renew_token_expires' => null,
            ]);
    }
}
