<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Idempotent bot-account met is_stats_viewer (geen admin, geen mailflow).
 * Wachtwoord uitsluitend uit env GP_PLANTJES_PASSWORD — nooit als CLI-argument,
 * nooit loggen of in de response.
 */
class CreateStatsLezer extends Command
{
    protected $signature = 'gp:stats-lezer {email : E-mailadres van de stats-lezer}';

    protected $description = 'Maak of werk een stats-lezer-account bij (is_stats_viewer, geen admin)';

    public function handle(): int
    {
        if (! Schema::hasColumn('users', 'is_stats_viewer')) {
            $this->error('Kolom users.is_stats_viewer ontbreekt — draai eerst migraties.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Ongeldig e-mailadres.');

            return self::FAILURE;
        }

        $password = (string) env('GP_PLANTJES_PASSWORD', '');
        if ($password === '') {
            $this->error('Env GP_PLANTJES_PASSWORD ontbreekt of is leeg.');

            return self::FAILURE;
        }

        try {
            $user = User::query()->where('email', $email)->first();
            $created = $user === null;

            if ($created) {
                $user = new User;
                $user->forceFill([
                    'email' => $email,
                    'name' => 'Plantjes (stats)',
                    // Cast 'hashed' hasht hier; geen Hash::make (anders dubbel).
                    'password' => $password,
                    'email_verified_at' => now(),
                    'active' => true,
                    'is_admin' => false,
                    'is_stats_viewer' => true,
                ]);
                $user->save();
            } else {
                $user->forceFill([
                    'name' => 'Plantjes (stats)',
                    'password' => $password,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'active' => true,
                    'is_admin' => false,
                    'is_stats_viewer' => true,
                ])->save();
            }
        } catch (Throwable $e) {
            $this->error('Stats-lezer bijwerken mislukt: '.class_basename($e));

            return self::FAILURE;
        }

        // Geen e-mailadres in CLI-output (PII).
        $this->info($created ? 'Stats-lezer aangemaakt.' : 'Stats-lezer bijgewerkt.');

        return self::SUCCESS;
    }
}
