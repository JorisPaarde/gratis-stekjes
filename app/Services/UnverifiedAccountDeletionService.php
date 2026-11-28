<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\UnverifiedDeletionNoticeDutch;
use App\Notifications\VerifyEmailReminderDay14Dutch;
use App\Notifications\VerifyEmailReminderDay7Dutch;
use App\Support\SafeMail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reeks voor onbevestigde accounts (achter UNVERIFIED_DELETION_ENABLED):
 * herinnering op dag 7 en dag 14, verwijdermail op dag 20, opruimen 7 dagen
 * later via AvgService::purgeUserAccount.
 *
 * De bestaande 24-uursherinnering (VerificationReminderService) blijft apart.
 *
 * Accounts met aanbod of berichten worden altijd overgeslagen.
 * Staging: versturen/verwijderen is fail-closed zonder REMINDER_TEST_RECIPIENTS.
 * Dry-run telt altijd, zonder allowlist, en zonder e-mailadressen, namen of id's.
 */
final class UnverifiedAccountDeletionService
{
    /**
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = true, int $limit = 50): array
    {
        if ($dryRun) {
            $stats = $this->counts();
            $stats['dry_run'] = true;
            $stats['sent_dag7'] = 0;
            $stats['sent_dag14'] = 0;
            $stats['sent'] = 0;
            $stats['skipped'] = 0;
            $stats['failed'] = 0;
            $stats['purged'] = 0;

            return $stats;
        }

        $stats = $this->emptyStats();
        $stats['dry_run'] = false;
        $stats['enabled'] = (bool) config('stekjes.unverified_deletion.enabled');

        if (! $stats['enabled']) {
            return $stats;
        }

        if (app()->environment('staging') && $this->normalizedAllowlist() === []) {
            Log::warning('unverified_deletion staging zonder REMINDER_TEST_RECIPIENTS; niets verstuurd of verwijderd');

            return $stats;
        }

        $dag7 = $this->sendReminders('day7', $limit);
        $dag14 = $this->sendReminders('day14', $limit);
        $notice = $this->sendNotices($limit);
        $stats['sent_dag7'] = $dag7['sent'];
        $stats['sent_dag14'] = $dag14['sent'];
        $stats['sent'] = $notice['sent'];
        $stats['skipped'] = $dag7['skipped'] + $dag14['skipped'] + $notice['skipped'];
        $stats['failed'] = $dag7['failed'] + $dag14['failed'] + $notice['failed'];
        $stats['purged'] = $this->purgeExpired($limit);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function counts(): array
    {
        $stats = $this->emptyStats();
        $stats['dry_run'] = true;
        $stats['enabled'] = (bool) config('stekjes.unverified_deletion.enabled');

        $ids = $this->unverifiedQuery()->orderBy('id')->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $stats['onbevestigd_actief'] = count($ids);
        if ($ids === []) {
            return $stats;
        }

        $skip = array_fill_keys($this->idsMetAanbodOfBerichten($ids), true);
        $stats['overgeslagen_met_aanbod_of_berichten'] = count($skip);

        $kandidaten = $this->unverifiedQuery()
            ->orderBy('id')
            ->get([
                'id',
                'created_at',
                'verification_reminder_day7_sent_at',
                'verification_reminder_day14_sent_at',
                'unverified_deletion_notice_sent_at',
            ]);

        $dag7Grens = now()->subDays($this->day7Days());
        $dag14Grens = now()->subDays($this->day14Days());
        $dag20Grens = now()->subDays($this->minAgeDays());
        $purgeGrens = now()->subDays($this->graceDays());
        $hasDay7 = Schema::hasColumn('users', 'verification_reminder_day7_sent_at');
        $hasDay14 = Schema::hasColumn('users', 'verification_reminder_day14_sent_at');
        $hasNotice = Schema::hasColumn('users', 'unverified_deletion_notice_sent_at');

        foreach ($kandidaten as $row) {
            $id = (int) $row->id;
            if (isset($skip[$id])) {
                continue;
            }

            $created = $row->created_at;
            $noticeAt = $hasNotice ? $row->unverified_deletion_notice_sent_at : null;
            $day7At = $hasDay7 ? $row->verification_reminder_day7_sent_at : null;
            $day14At = $hasDay14 ? $row->verification_reminder_day14_sent_at : null;

            if ($noticeAt !== null) {
                if ($noticeAt <= $purgeGrens) {
                    $stats['eligible_verwijdering']++;
                } else {
                    $stats['notice_in_termijn']++;
                }

                continue;
            }

            if ($created <= $dag20Grens) {
                $stats['eligible_verwijdermail']++;

                continue;
            }

            if ($created <= $dag14Grens) {
                if ($day14At === null) {
                    $stats['eligible_herinnering_dag14']++;
                }

                continue;
            }

            if ($created <= $dag7Grens && $day7At === null) {
                $stats['eligible_herinnering_dag7']++;
            }
        }

        return $stats;
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendReminders(string $welke, int $limit = 50): array
    {
        $kolom = $welke === 'day14'
            ? 'verification_reminder_day14_sent_at'
            : 'verification_reminder_day7_sent_at';
        $notification = $welke === 'day14'
            ? VerifyEmailReminderDay14Dutch::class
            : VerifyEmailReminderDay7Dutch::class;

        if (! (bool) config('stekjes.unverified_deletion.enabled')
            || ! Schema::hasColumn('users', $kolom)) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $allowlist = $this->normalizedAllowlist();
        if (app()->environment('staging') && $allowlist === []) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $ids = $welke === 'day14' ? $this->eligibleDay14Ids() : $this->eligibleDay7Ids();
        if ($allowlist !== []) {
            $ids = $this->filterAllowlist($ids, $allowlist);
        }

        return $this->sendToIds($ids, $kolom, $notification, $limit);
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendNotices(int $limit = 50): array
    {
        if (! (bool) config('stekjes.unverified_deletion.enabled')
            || ! Schema::hasColumn('users', 'unverified_deletion_notice_sent_at')) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $allowlist = $this->normalizedAllowlist();
        if (app()->environment('staging') && $allowlist === []) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $ids = $this->eligibleNoticeIds();
        if ($allowlist !== []) {
            $ids = $this->filterAllowlist($ids, $allowlist);
        }

        $verwijderOp = $this->verwijderOpVanaf(now()->toImmutable());
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $limit = max(1, $limit);

        foreach (array_slice($ids, 0, $limit) as $userId) {
            if ($this->heeftAanbodOfBerichten($userId) || ! $this->claimNoticeSlot($userId)) {
                $skipped++;

                continue;
            }

            try {
                $user = User::query()->members()->whereKey($userId)->first();
                if ($user === null || $user->email_verified_at !== null || ! $user->active) {
                    $skipped++;

                    continue;
                }
                $user->rotateEmailVerificationToken();
                $ok = SafeMail::notify($user, new UnverifiedDeletionNoticeDutch($verwijderOp));
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('unverified_deletion mail mislukt: '.$e::class);
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }

    public function purgeExpired(int $limit = 50): int
    {
        $purged = 0;
        $limit = max(1, $limit);

        if (! (bool) config('stekjes.unverified_deletion.enabled')
            || ! Schema::hasColumn('users', 'unverified_deletion_notice_sent_at')) {
            return 0;
        }

        $allowlist = $this->normalizedAllowlist();
        if (app()->environment('staging') && $allowlist === []) {
            return 0;
        }

        $query = $this->unverifiedQuery()
            ->whereNotNull('unverified_deletion_notice_sent_at')
            ->where('unverified_deletion_notice_sent_at', '<=', now()->subDays($this->graceDays()))
            ->orderBy('id');

        if ($allowlist !== []) {
            $query->whereIn('email', $allowlist);
        }

        $users = $query->limit($limit)->get();
        $avg = app(AvgService::class);

        foreach ($users as $user) {
            if ($this->heeftAanbodOfBerichten((int) $user->id)) {
                continue;
            }

            try {
                $avg->purgeUserAccount($user);
                $purged++;
            } catch (Throwable $e) {
                Log::error('unverified_deletion purge mislukt: '.$e::class);
            }
        }

        if ($purged > 0) {
            Log::info('unverified_deletion purge klaar', ['purged' => $purged]);
        }

        return $purged;
    }

    public function graceDays(): int
    {
        return max(1, (int) config('stekjes.unverified_deletion.grace_days', 7));
    }

    public function minAgeDays(): int
    {
        return max(1, (int) config('stekjes.unverified_deletion.min_age_days', 20));
    }

    public function day7Days(): int
    {
        return max(1, (int) config('stekjes.unverified_deletion.day7_days', 7));
    }

    public function day14Days(): int
    {
        return max($this->day7Days() + 1, (int) config('stekjes.unverified_deletion.day14_days', 14));
    }

    public function verwijderOpVanaf(CarbonImmutable $vanaf): CarbonImmutable
    {
        return $vanaf->addDays($this->graceDays());
    }

    /**
     * @return list<int>
     */
    public function eligibleDay7Ids(): array
    {
        if (! Schema::hasColumn('users', 'verification_reminder_day7_sent_at')) {
            return [];
        }

        $ids = $this->unverifiedQuery()
            ->where('created_at', '<=', now()->subDays($this->day7Days()))
            ->where('created_at', '>', now()->subDays($this->day14Days()))
            ->whereNull('verification_reminder_day7_sent_at')
            ->when(
                Schema::hasColumn('users', 'unverified_deletion_notice_sent_at'),
                static fn (Builder $q): Builder => $q->whereNull('unverified_deletion_notice_sent_at')
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $this->zonderAanbodOfBerichten($ids);
    }

    /**
     * @return list<int>
     */
    public function eligibleDay14Ids(): array
    {
        if (! Schema::hasColumn('users', 'verification_reminder_day14_sent_at')) {
            return [];
        }

        $ids = $this->unverifiedQuery()
            ->where('created_at', '<=', now()->subDays($this->day14Days()))
            ->where('created_at', '>', now()->subDays($this->minAgeDays()))
            ->whereNull('verification_reminder_day14_sent_at')
            ->when(
                Schema::hasColumn('users', 'unverified_deletion_notice_sent_at'),
                static fn (Builder $q): Builder => $q->whereNull('unverified_deletion_notice_sent_at')
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $this->zonderAanbodOfBerichten($ids);
    }

    /**
     * @return list<int>
     */
    public function eligibleNoticeIds(): array
    {
        if (! Schema::hasColumn('users', 'unverified_deletion_notice_sent_at')) {
            return [];
        }

        $ids = $this->unverifiedQuery()
            ->where('created_at', '<=', now()->subDays($this->minAgeDays()))
            ->whereNull('unverified_deletion_notice_sent_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $this->zonderAanbodOfBerichten($ids);
    }

    public function claimNoticeSlot(int $userId): bool
    {
        return $this->claimKolom($userId, 'unverified_deletion_notice_sent_at');
    }

    public function claimDay7Slot(int $userId): bool
    {
        return $this->claimKolom($userId, 'verification_reminder_day7_sent_at');
    }

    public function claimDay14Slot(int $userId): bool
    {
        return $this->claimKolom($userId, 'verification_reminder_day14_sent_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'enabled' => (bool) config('stekjes.unverified_deletion.enabled'),
            'dry_run' => true,
            'grace_days' => $this->graceDays(),
            'min_age_days' => $this->minAgeDays(),
            'onbevestigd_actief' => 0,
            'eligible_herinnering_dag7' => 0,
            'eligible_herinnering_dag14' => 0,
            'eligible_verwijdermail' => 0,
            'notice_in_termijn' => 0,
            'eligible_verwijdering' => 0,
            'overgeslagen_met_aanbod_of_berichten' => 0,
            'sent_dag7' => 0,
            'sent_dag14' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'purged' => 0,
        ];
    }

    /**
     * @return Builder<User>
     */
    private function unverifiedQuery(): Builder
    {
        $query = User::query()
            ->members()
            ->where('active', true)
            ->whereNull('email_verified_at');

        if (Schema::hasColumn('users', 'is_admin')) {
            $query->where('is_admin', false);
        }

        return $query;
    }

    private function claimKolom(int $userId, string $kolom): bool
    {
        if ($userId < 1 || ! Schema::hasColumn('users', $kolom)) {
            return false;
        }

        $affected = User::query()
            ->whereKey($userId)
            ->where('active', true)
            ->whereNull('email_verified_at')
            ->whereNull($kolom)
            ->update([$kolom => now()]);

        return $affected === 1;
    }

    /**
     * @param  list<int>  $ids
     * @param  class-string  $notification
     * @return array{sent: int, skipped: int, failed: int}
     */
    private function sendToIds(array $ids, string $kolom, string $notification, int $limit): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $limit = max(1, $limit);

        foreach (array_slice($ids, 0, $limit) as $userId) {
            if ($this->heeftAanbodOfBerichten($userId) || ! $this->claimKolom($userId, $kolom)) {
                $skipped++;

                continue;
            }

            try {
                $user = User::query()->members()->whereKey($userId)->first();
                if ($user === null || $user->email_verified_at !== null || ! $user->active) {
                    $skipped++;

                    continue;
                }
                $user->rotateEmailVerificationToken();
                $ok = SafeMail::notify($user, new $notification);
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('unverified_deletion herinnering mislukt: '.$e::class);
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $allowlist
     * @return list<int>
     */
    private function filterAllowlist(array $ids, array $allowlist): array
    {
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->whereIn('email', $allowlist)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function zonderAanbodOfBerichten(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $skip = array_fill_keys($this->idsMetAanbodOfBerichten($ids), true);

        return array_values(array_filter($ids, static fn (int $id): bool => ! isset($skip[$id])));
    }

    public function heeftAanbodOfBerichten(int $userId): bool
    {
        return $this->idsMetAanbodOfBerichten([$userId]) !== [];
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function idsMetAanbodOfBerichten(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $gevonden = [];

        if (Schema::hasTable('aanbod')) {
            foreach (DB::table('aanbod')->whereIn('user_id', $userIds)->pluck('user_id') as $id) {
                $gevonden[] = (int) $id;
            }
        }

        if (Schema::hasTable('conversations')) {
            foreach (DB::table('conversations')->whereIn('user_a_id', $userIds)->pluck('user_a_id') as $id) {
                $gevonden[] = (int) $id;
            }
            foreach (DB::table('conversations')->whereIn('user_b_id', $userIds)->pluck('user_b_id') as $id) {
                $gevonden[] = (int) $id;
            }
        }

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'sender_id')) {
            foreach (DB::table('messages')->whereIn('sender_id', $userIds)->pluck('sender_id') as $id) {
                $gevonden[] = (int) $id;
            }
        }

        return array_values(array_unique($gevonden));
    }

    /**
     * @return list<string>
     */
    private function normalizedAllowlist(): array
    {
        $raw = array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            (array) config('stekjes.unverified_deletion.test_recipients', [])
        );

        return array_values(array_filter(
            $raw,
            static fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
