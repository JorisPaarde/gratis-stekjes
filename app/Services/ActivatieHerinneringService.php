<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ActivatieHerinneringMail;
use App\Models\User;
use App\Support\LedenupdateMailContent;
use App\Support\SafeMail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Eén tipmail ~2 dagen na e-mailbevestiging als er nog geen aanbod is.
 * Alleen accounts met email_verified_at ≥ ACTIVATION_REMINDER_ENABLED_AT
 * (geen terugwerkende kracht op bestaande leden).
 *
 * Staging: REMINDER_TEST_RECIPIENTS verplicht (fail-closed).
 * Versturen pas ná atomische claim op activatie_herinnering_sent_at.
 */
final class ActivatieHerinneringService
{
    /**
     * @return array{enabled: bool, sent: int, skipped: int, failed: int, eligible: int, dry_run: bool}
     */
    public function run(int $limit = 50, bool $dryRun = false): array
    {
        $empty = ['enabled' => false, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'eligible' => 0, 'dry_run' => $dryRun];
        $flagOn = (bool) config('stekjes.activation_reminder.enabled');

        // Versturen vereist de vlag; dry-run mag wel aantallen tonen (mits ENABLED_AT).
        if (! $flagOn && ! $dryRun) {
            return $empty;
        }

        if (! Schema::hasColumn('users', 'activatie_herinnering_sent_at')) {
            return $empty;
        }

        $enabledAtRaw = trim((string) config('stekjes.activation_reminder.enabled_at', ''));
        if ($enabledAtRaw === '') {
            if ($flagOn) {
                Log::warning('activation_reminder enabled zonder ENABLED_AT; niets verstuurd');
            }

            return $empty;
        }

        try {
            $enabledAt = CarbonImmutable::parse($enabledAtRaw);
        } catch (Throwable) {
            if ($flagOn) {
                Log::warning('activation_reminder ENABLED_AT ongeldig; niets verstuurd');
            }

            return $empty;
        }

        $allowlist = $this->normalizedAllowlist();

        // Staging fail-closed: zonder geldige allowlist nooit alle accounts mailen.
        if (app()->environment('staging') && $allowlist === []) {
            Log::warning('activation_reminder staging zonder REMINDER_TEST_RECIPIENTS; niets verstuurd');

            return ['enabled' => $flagOn, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'eligible' => 0, 'dry_run' => $dryRun];
        }

        $query = $this->eligibleQuery($enabledAt, $allowlist);
        $eligible = (clone $query)->count();

        if ($dryRun) {
            return [
                'enabled' => $flagOn,
                'sent' => 0,
                'skipped' => 0,
                'failed' => 0,
                'eligible' => $eligible,
                'dry_run' => true,
            ];
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($query->limit($limit)->get() as $user) {
            if (! $this->claimReminderSlot((int) $user->id)) {
                $skipped++;

                continue;
            }

            try {
                $user->refresh();
                $ok = SafeMail::mailable(
                    (string) $user->email,
                    $this->buildMail($user),
                    (int) $user->id,
                );
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('activation_reminder mislukt: '.$e::class);
            }
        }

        return [
            'enabled' => true,
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'eligible' => $eligible,
            'dry_run' => false,
        ];
    }

    /**
     * Eligible: actief lid, bevestigd ná livegang, ≥ after_hours geleden,
     * nog geen aanbod, nog niet herinnerd, tipmail niet uitgezet.
     *
     * @param  list<string>  $allowlist
     * @return Builder<User>
     */
    public function eligibleQuery(CarbonImmutable $enabledAt, array $allowlist = []): Builder
    {
        $afterHours = max(1, (int) config('stekjes.activation_reminder.after_hours', 48));
        $cutoff = now()->subHours($afterHours);

        $query = User::query()
            ->members()
            ->where('active', true)
            ->whereNotNull('email_verified_at')
            ->whereNull('activatie_herinnering_sent_at')
            ->where('email_verified_at', '>=', $enabledAt)
            ->where('email_verified_at', '<=', $cutoff)
            ->whereDoesntHave('aanbod')
            ->orderBy('id');

        if (Schema::hasColumn('users', 'activatieherinnering')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('activatieherinnering')
                    ->orWhere('activatieherinnering', true);
            });
        }

        if ($allowlist !== []) {
            $query->whereIn('email', $allowlist);
        }

        return $query;
    }

    public function buildMail(User $user): ActivatieHerinneringMail
    {
        [$voornaam] = LedenupdateMailContent::aanhef($user);

        return new ActivatieHerinneringMail(
            voornaam: $voornaam,
            aanbiedenUrl: route('aanbieden'),
            uitzettenUrl: URL::signedRoute('activatieherinnering.uit', ['user' => $user->id]),
        );
    }

    /**
     * Claim het herinnerings-slot atomisch (compare-and-set).
     * true = deze worker mag versturen; false = andere worker won of niet meer eligible.
     */
    public function claimReminderSlot(int $userId): bool
    {
        if ($userId < 1 || ! Schema::hasColumn('users', 'activatie_herinnering_sent_at')) {
            return false;
        }

        $query = User::query()
            ->whereKey($userId)
            ->where('active', true)
            ->whereNotNull('email_verified_at')
            ->whereNull('activatie_herinnering_sent_at')
            ->whereDoesntHave('aanbod');

        if (Schema::hasColumn('users', 'activatieherinnering')) {
            $query->where(function (Builder $q): void {
                $q->whereNull('activatieherinnering')
                    ->orWhere('activatieherinnering', true);
            });
        }

        $affected = $query->update(['activatie_herinnering_sent_at' => now()]);

        return $affected === 1;
    }

    /**
     * @return list<string>
     */
    public function normalizedAllowlist(): array
    {
        $raw = array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            (array) config('stekjes.activation_reminder.test_recipients', [])
        );

        return array_values(array_filter(
            $raw,
            static fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
