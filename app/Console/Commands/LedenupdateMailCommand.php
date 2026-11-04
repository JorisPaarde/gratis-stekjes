<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\LedenupdateMail;
use App\Models\LedenupdateMailSent;
use App\Models\User;
use App\Services\MilestoneService;
use App\Support\AdminDashboard;
use App\Support\LedenupdateMailContent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Eenmalige ledenupdate-mail via site-SMTP (nooit Resend).
 * Output bevat nooit e-mailadressen of straatadressen.
 */
class LedenupdateMailCommand extends Command
{
    protected $signature = 'gp:ledenupdate-mail
        {--dry-run : Toon ontvangers + gerenderde mail (standaard zonder --send)}
        {--ids : Print alleen de ontvanger-ids (kommagescheiden, geordend)}
        {--send : Verstuur precies één mail}
        {--id= : Lid-id voor --send}';

    protected $description = 'Eenmalige ledenupdate-mail (dry-run / ids / send één lid)';

    public function handle(MilestoneService $milestones): int
    {
        if ($this->option('ids')) {
            return $this->printIds();
        }

        if ($this->option('send')) {
            return $this->sendOne($milestones);
        }

        return $this->dryRun($milestones);
    }

    /**
     * Actieve + geverifieerde leden (members() minus admins/stats-lezer), zelfde selectie
     * als gp:postcode-herinnering-lijst --alle maar alleen active+verified.
     *
     * @return Builder<User>
     */
    public static function recipientsQuery(): Builder
    {
        $admins = AdminDashboard::allowedEmails();

        return User::query()
            ->members()
            ->where('active', true)
            ->whereNotNull('email_verified_at')
            ->when($admins !== [], fn ($q) => $q->whereNotIn(DB::raw('LOWER(email)'), $admins))
            ->when(Schema::hasColumn('users', 'is_admin'), fn ($q) => $q->where('is_admin', false))
            ->orderBy('id');
    }

    /** @return list<int> */
    public static function recipientIds(): array
    {
        return self::recipientsQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function printIds(): int
    {
        $ids = self::recipientIds();
        $this->line(implode(', ', $ids));

        return self::SUCCESS;
    }

    private function dryRun(MilestoneService $milestones): int
    {
        $failed = [];
        $rows = [];
        $ids = [];

        self::recipientsQuery()
            ->select(['id', 'name', 'email', 'country', 'locatie_label', 'postcode_gebied', 'woonplaats', 'lat', 'lon', 'is_admin', 'is_stats_viewer', 'active', 'email_verified_at'])
            ->chunkById(200, function ($users) use ($milestones, &$failed, &$rows, &$ids): void {
                foreach ($users as $user) {
                    $id = (int) $user->id;
                    $ids[] = $id;
                    $missing = LedenupdateMailContent::missingFields($user, $milestones);
                    if ($missing !== []) {
                        $failed[] = sprintf('#%d mist: %s', $id, implode(', ', $missing));

                        continue;
                    }

                    $data = LedenupdateMailContent::placeholders($user, $milestones);
                    if ($data === null) {
                        $failed[] = sprintf('#%d mist: placeholders', $id);

                        continue;
                    }

                    $rows[] = [
                        'id' => $id,
                        'data' => $data,
                    ];
                }
            });

        $this->info('Ledenupdate-mail (dry-run, geen mail verstuurd)');
        $this->line('Totaal kandidaten: '.count($ids));

        if ($failed !== []) {
            $this->error('Dry-run mislukt: lege verplichte velden');
            foreach ($failed as $line) {
                $this->line($line);
            }

            return self::FAILURE;
        }

        foreach ($rows as $row) {
            /** @var array{naam: string, nr: int, tier: int, vraag_postcode: bool} $data */
            $data = $row['data'];
            $this->line(sprintf(
                '  #%d · lidnummer %d · tier %d · naam %s · postcode-zin %s',
                $row['id'],
                $data['nr'],
                $data['tier'],
                'ja',
                $data['vraag_postcode'] ? 'ja' : 'nee',
            ));
            $this->line('  Onderwerp: '.LedenupdateMailContent::SUBJECT);
            $this->line('  --- body ---');
            // Geen e-mailadres in output; body bevat alleen {naam}/URLs/tekst.
            foreach (explode("\n", LedenupdateMailContent::plainBody($data)) as $line) {
                $this->line('  '.$line);
            }
            $this->line('  --- /body ---');
            $this->newLine();
        }

        $this->line('Lid-id\'s: '.($ids !== [] ? implode(', ', $ids) : '-'));

        return self::SUCCESS;
    }

    private function sendOne(MilestoneService $milestones): int
    {
        $rawId = $this->option('id');
        if ($rawId === null || $rawId === '' || ! ctype_digit((string) $rawId)) {
            $this->error('Gebruik --send --id=N met een geldig lid-id.');

            return self::FAILURE;
        }

        $id = (int) $rawId;
        $inSet = self::recipientsQuery()->where('id', $id)->exists();
        if (! $inSet) {
            $this->error(sprintf('#%d staat niet in de ontvangersset (active+verified members).', $id));

            return self::FAILURE;
        }

        $existing = LedenupdateMailSent::query()->where('user_id', $id)->first();
        if ($existing !== null && $existing->isSent()) {
            $this->info(sprintf('#%d al verstuurd op %s — overgeslagen.', $id, optional($existing->sent_at)->toIso8601String() ?? '?'));

            return self::SUCCESS;
        }

        $user = User::query()->findOrFail($id);
        $data = LedenupdateMailContent::placeholders($user, $milestones);
        if ($data === null) {
            $missing = LedenupdateMailContent::missingFields($user, $milestones);
            $this->error(sprintf('#%d mist: %s', $id, implode(', ', $missing) ?: 'placeholders'));

            return self::FAILURE;
        }

        try {
            Mail::to($user->email)->send(new LedenupdateMail($data));
        } catch (Throwable $e) {
            $summary = class_basename($e).': '.mb_substr(
                (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $e->getMessage()),
                0,
                200
            );

            LedenupdateMailSent::query()->updateOrCreate(
                ['user_id' => $id],
                [
                    'sent_at' => null,
                    'status' => 'error',
                    'error' => mb_substr($summary, 0, 255),
                ]
            );

            $this->error(sprintf('#%d SMTP-fout: %s', $id, $summary));

            return self::FAILURE;
        }

        LedenupdateMailSent::query()->updateOrCreate(
            ['user_id' => $id],
            [
                'sent_at' => now(),
                'status' => 'sent',
                'error' => null,
            ]
        );

        $this->info(sprintf('#%d verstuurd.', $id));

        return self::SUCCESS;
    }
}
