<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MailLog;
use App\Models\User;
use App\Notifications\UnverifiedDeletionNoticeDutch;
use App\Support\BevestigingsmailStats;
use App\Support\SafeMail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Verwijdermail + opruimen van onbevestigde accounts.
 *
 * Selectie waarschuwing: actief lid, e-mail onbevestigd, account ≥ min_age_days
 * (standaard 20), minstens min_reminders herinneringen (standaard 3, zelfde
 * telling als beheer: VerifyEmailDutch ná de eerste + VerifyEmailReminderDutch),
 * nog geen verwijdermail.
 *
 * Ontbreekt de auto-herinnering in mail_log (retentie 90 dagen) maar is
 * verification_reminder_sent_at gezet, dan telt die als 1 herinnering.
 *
 * Default UIT (UNVERIFIED_DELETION_ENABLED). Staging: versturen/verwijderen
 * is fail-closed zonder REMINDER_TEST_RECIPIENTS. Dry-run telt altijd, zonder
 * allowlist, en zonder e-mailadressen, namen of id's in de uitvoer.
 */
final class UnverifiedAccountDeletionService
{
    /**
     * @return array{
     *   enabled: bool,
     *   dry_run: bool,
     *   grace_days: int,
     *   min_age_days: int,
     *   min_reminders: int,
     *   onbevestigd_actief: int,
     *   onbevestigd_min_leeftijd: int,
     *   herinneringen_0: int,
     *   herinneringen_1: int,
     *   herinneringen_2: int,
     *   herinneringen_3plus: int,
     *   met_auto_herinnering: int,
     *   eligible_verwijdermail: int,
     *   notice_in_termijn: int,
     *   eligible_verwijdering: int,
     *   met_aanbod: int,
     *   met_gesprekken: int,
     *   met_ervaringen: int,
     *   met_vereniging: int,
     *   sent: int,
     *   skipped: int,
     *   failed: int,
     *   purged: int
     * }
     */
    public function run(bool $dryRun = true, int $limit = 50): array
    {
        if ($dryRun) {
            $stats = $this->counts();
            $stats['dry_run'] = true;
            $stats['sent'] = 0;
            $stats['skipped'] = 0;
            $stats['failed'] = 0;
            $stats['purged'] = 0;

            return $stats;
        }

        // Cron-pad: geen zware telling, alleen versturen/verwijderen als de vlag aan staat.
        $stats = [
            'enabled' => (bool) config('stekjes.unverified_deletion.enabled'),
            'dry_run' => false,
            'grace_days' => $this->graceDays(),
            'min_age_days' => $this->minAgeDays(),
            'min_reminders' => $this->minReminders(),
            'onbevestigd_actief' => 0,
            'onbevestigd_min_leeftijd' => 0,
            'herinneringen_0' => 0,
            'herinneringen_1' => 0,
            'herinneringen_2' => 0,
            'herinneringen_3plus' => 0,
            'met_auto_herinnering' => 0,
            'eligible_verwijdermail' => 0,
            'notice_in_termijn' => 0,
            'eligible_verwijdering' => 0,
            'met_aanbod' => 0,
            'met_gesprekken' => 0,
            'met_ervaringen' => 0,
            'met_vereniging' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'purged' => 0,
        ];

        if (! $stats['enabled']) {
            return $stats;
        }

        if (app()->environment('staging') && $this->normalizedAllowlist() === []) {
            Log::warning('unverified_deletion staging zonder REMINDER_TEST_RECIPIENTS; niets verstuurd of verwijderd');

            return $stats;
        }

        $send = $this->sendNotices($limit);
        $stats['sent'] = $send['sent'];
        $stats['skipped'] = $send['skipped'];
        $stats['failed'] = $send['failed'];
        $stats['purged'] = $this->purgeExpired($limit);

        return $stats;
    }

    /**
     * @return array{
     *   enabled: bool,
     *   dry_run: bool,
     *   grace_days: int,
     *   min_age_days: int,
     *   min_reminders: int,
     *   onbevestigd_actief: int,
     *   onbevestigd_min_leeftijd: int,
     *   herinneringen_0: int,
     *   herinneringen_1: int,
     *   herinneringen_2: int,
     *   herinneringen_3plus: int,
     *   met_auto_herinnering: int,
     *   eligible_verwijdermail: int,
     *   notice_in_termijn: int,
     *   eligible_verwijdering: int,
     *   met_aanbod: int,
     *   met_gesprekken: int,
     *   met_ervaringen: int,
     *   met_vereniging: int,
     *   sent: int,
     *   skipped: int,
     *   failed: int,
     *   purged: int
     * }
     */
    public function counts(): array
    {
        $grace = $this->graceDays();
        $minAge = $this->minAgeDays();
        $minReminders = $this->minReminders();
        $empty = [
            'enabled' => (bool) config('stekjes.unverified_deletion.enabled'),
            'dry_run' => true,
            'grace_days' => $grace,
            'min_age_days' => $minAge,
            'min_reminders' => $minReminders,
            'onbevestigd_actief' => 0,
            'onbevestigd_min_leeftijd' => 0,
            'herinneringen_0' => 0,
            'herinneringen_1' => 0,
            'herinneringen_2' => 0,
            'herinneringen_3plus' => 0,
            'met_auto_herinnering' => 0,
            'eligible_verwijdermail' => 0,
            'notice_in_termijn' => 0,
            'eligible_verwijdering' => 0,
            'met_aanbod' => 0,
            'met_gesprekken' => 0,
            'met_ervaringen' => 0,
            'met_vereniging' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'purged' => 0,
        ];

        $onbevestigd = $this->unverifiedQuery();
        $empty['onbevestigd_actief'] = (clone $onbevestigd)->count();

        $oud = (clone $onbevestigd)->where('created_at', '<=', now()->subDays($minAge));
        $empty['onbevestigd_min_leeftijd'] = (clone $oud)->count();

        if ($empty['onbevestigd_min_leeftijd'] === 0) {
            return $empty;
        }

        $rows = (clone $oud)
            ->get(['id', 'verification_reminder_sent_at', 'unverified_deletion_notice_sent_at']);

        $ids = $rows->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $herinneringen = $this->herinneringenVoor($ids);
        $eligibleNoticeIds = [];
        $hasNoticeCol = Schema::hasColumn('users', 'unverified_deletion_notice_sent_at');
        $cutoff = now()->subDays($grace);

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $n = $herinneringen[$id] ?? 0;
            if ($n <= 0) {
                $empty['herinneringen_0']++;
            } elseif ($n === 1) {
                $empty['herinneringen_1']++;
            } elseif ($n === 2) {
                $empty['herinneringen_2']++;
            } else {
                $empty['herinneringen_3plus']++;
            }

            if ($row->verification_reminder_sent_at !== null) {
                $empty['met_auto_herinnering']++;
            }

            $noticeAt = $hasNoticeCol ? $row->unverified_deletion_notice_sent_at : null;
            if ($noticeAt === null) {
                if ($n >= $minReminders) {
                    $eligibleNoticeIds[] = $id;
                }
            } elseif ($noticeAt <= $cutoff) {
                $empty['eligible_verwijdering']++;
            } else {
                $empty['notice_in_termijn']++;
            }
        }

        $empty['eligible_verwijdermail'] = count($eligibleNoticeIds);

        // Gekoppelde data: van de accounts die in aanmerking komen voor de
        // verwijdermail, plus die al een mail kregen (wachtend of rijp).
        $relevantIds = $eligibleNoticeIds;
        foreach ($rows as $row) {
            if ($row->unverified_deletion_notice_sent_at !== null) {
                $relevantIds[] = (int) $row->id;
            }
        }
        $relevantIds = array_values(array_unique($relevantIds));
        if ($relevantIds === [] && $empty['onbevestigd_min_leeftijd'] > 0) {
            // Nog niemand met 3 herinneringen: toon gekoppelde data voor alle ≥20 dagen.
            $relevantIds = $ids;
        }
        $related = $this->relatedCounts($relevantIds);
        $empty['met_aanbod'] = $related['met_aanbod'];
        $empty['met_gesprekken'] = $related['met_gesprekken'];
        $empty['met_ervaringen'] = $related['met_ervaringen'];
        $empty['met_vereniging'] = $related['met_vereniging'];

        return $empty;
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendNotices(int $limit = 50): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $limit = max(1, $limit);

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
            $allowedIds = User::query()
                ->whereIn('id', $ids)
                ->whereIn('email', $allowlist)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $ids = $allowedIds;
        }

        $verwijderOp = $this->verwijderOpVanaf(now()->toImmutable());

        foreach (array_slice($ids, 0, $limit) as $userId) {
            if (! $this->claimNoticeSlot($userId)) {
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

    public function minReminders(): int
    {
        return max(1, (int) config('stekjes.unverified_deletion.min_reminders', 3));
    }

    public function verwijderOpVanaf(CarbonImmutable $vanaf): CarbonImmutable
    {
        return $vanaf->addDays($this->graceDays());
    }

    /**
     * @return list<int>
     */
    public function eligibleNoticeIds(): array
    {
        if (! Schema::hasColumn('users', 'unverified_deletion_notice_sent_at')) {
            return [];
        }

        $kandidaten = $this->unverifiedQuery()
            ->where('created_at', '<=', now()->subDays($this->minAgeDays()))
            ->whereNull('unverified_deletion_notice_sent_at')
            ->orderBy('id')
            ->get(['id', 'verification_reminder_sent_at']);

        $ids = $kandidaten->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $herinneringen = $this->herinneringenVoor($ids);
        $min = $this->minReminders();
        $out = [];
        foreach ($ids as $id) {
            if (($herinneringen[$id] ?? 0) >= $min) {
                $out[] = $id;
            }
        }

        return $out;
    }

    public function claimNoticeSlot(int $userId): bool
    {
        if ($userId < 1 || ! Schema::hasColumn('users', 'unverified_deletion_notice_sent_at')) {
            return false;
        }

        $affected = User::query()
            ->whereKey($userId)
            ->where('active', true)
            ->whereNull('email_verified_at')
            ->whereNull('unverified_deletion_notice_sent_at')
            ->update(['unverified_deletion_notice_sent_at' => now()]);

        return $affected === 1;
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

    /**
     * @param  list<int>  $userIds
     * @return array<int, int> user_id => aantal herinneringen
     */
    private function herinneringenVoor(array $userIds): array
    {
        $acc = [];
        foreach ($userIds as $id) {
            $acc[$id] = 0;
        }
        if ($userIds === [] || ! Schema::hasTable('mail_log')) {
            return $this->aanvullenMetAutoKolom($acc, $userIds);
        }

        $rows = MailLog::query()
            ->selectRaw('user_id, type, status, COUNT(*) as n')
            ->whereIn('user_id', $userIds)
            ->whereIn('type', BevestigingsmailStats::TYPES)
            ->groupBy('user_id', 'type', 'status')
            ->get();

        /** @var array<int, array{verify: int, auto: int}> $raw */
        $raw = [];
        foreach ($userIds as $id) {
            $raw[$id] = ['verify' => 0, 'auto' => 0];
        }
        foreach ($rows as $row) {
            if ($row->status !== 'ok') {
                continue;
            }
            $uid = (int) $row->user_id;
            if (! isset($raw[$uid])) {
                continue;
            }
            if ($row->type === 'VerifyEmailDutch') {
                $raw[$uid]['verify'] += (int) $row->n;
            } elseif ($row->type === 'VerifyEmailReminderDutch') {
                $raw[$uid]['auto'] += (int) $row->n;
            }
        }

        foreach ($raw as $uid => $s) {
            $acc[$uid] = max(0, $s['verify'] - 1) + $s['auto'];
        }

        return $this->aanvullenMetAutoKolom($acc, $userIds);
    }

    /**
     * Als mail_log de auto-herinnering niet meer heeft, maar de kolom wel.
     *
     * @param  array<int, int>  $acc
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function aanvullenMetAutoKolom(array $acc, array $userIds): array
    {
        if ($userIds === [] || ! Schema::hasColumn('users', 'verification_reminder_sent_at')) {
            return $acc;
        }

        $autoIds = User::query()
            ->whereIn('id', $userIds)
            ->whereNotNull('verification_reminder_sent_at')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $autoInLog = [];
        if (Schema::hasTable('mail_log') && $autoIds !== []) {
            $autoInLog = MailLog::query()
                ->whereIn('user_id', $autoIds)
                ->where('type', 'VerifyEmailReminderDutch')
                ->where('status', 'ok')
                ->distinct()
                ->pluck('user_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }
        $autoInLogLookup = array_flip($autoInLog);

        foreach ($autoIds as $id) {
            if (! isset($autoInLogLookup[$id])) {
                $acc[$id] = ($acc[$id] ?? 0) + 1;
            }
        }

        return $acc;
    }

    /**
     * @param  list<int>  $userIds
     * @return array{met_aanbod: int, met_gesprekken: int, met_ervaringen: int, met_vereniging: int}
     */
    private function relatedCounts(array $userIds): array
    {
        $out = [
            'met_aanbod' => 0,
            'met_gesprekken' => 0,
            'met_ervaringen' => 0,
            'met_vereniging' => 0,
        ];
        if ($userIds === []) {
            return $out;
        }

        if (Schema::hasTable('aanbod')) {
            $out['met_aanbod'] = DB::table('aanbod')
                ->whereIn('user_id', $userIds)
                ->distinct()
                ->count('user_id');
        }

        if (Schema::hasTable('conversations')) {
            $a = DB::table('conversations')->whereIn('user_a_id', $userIds)->pluck('user_a_id');
            $b = DB::table('conversations')->whereIn('user_b_id', $userIds)->pluck('user_b_id');
            $out['met_gesprekken'] = $a->merge($b)->unique()->count();
        }

        if (Schema::hasTable('ervaringen')) {
            $out['met_ervaringen'] = DB::table('ervaringen')
                ->whereIn('user_id', $userIds)
                ->distinct()
                ->count('user_id');
        }

        if (Schema::hasTable('vereniging_user')) {
            $out['met_vereniging'] = (int) DB::table('vereniging_user')
                ->whereIn('user_id', $userIds)
                ->distinct()
                ->count('user_id');
        }

        return $out;
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
