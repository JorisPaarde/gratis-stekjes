<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Snelkoppeling + optionele account-toegang tot /beheer.
 * Match alleen op unieke users.email — nooit op weergavenaam —
 * of op de account-vlag users.is_admin (knop «Maak beheerder» op /beheer/gebruikers/{id}).
 */
final class AdminDashboard
{
    /**
     * @return list<string> genormaliseerde e-mails (lowercase)
     */
    public static function allowedEmails(): array
    {
        $configured = config('stekjes.admin_dashboard_emails', []);
        if (! is_array($configured)) {
            $configured = [];
        }

        $emails = [];
        foreach ($configured as $email) {
            $normalized = strtolower(trim((string) $email));
            if ($normalized !== '') {
                $emails[] = $normalized;
            }
        }

        if ($emails !== []) {
            return array_values(array_unique($emails));
        }

        $fallback = strtolower(trim((string) config('stekjes.admin_email', '')));

        return $fallback !== '' ? [$fallback] : [];
    }

    public static function userMayAccess(?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return false;
        }

        return in_array($email, self::allowedEmails(), true);
    }
}
