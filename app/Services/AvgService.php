<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class AvgService
{
    /**
     * Export gebruikersgegevens (geen password/tokens).
     *
     * @return array<string, mixed>
     */
    public function exportUserData(User $user): array
    {
        $aanbod = Aanbod::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'type', 'tekst', 'visible', 'created_at', 'updated_at', 'expires_at'])
            ->toArray();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'version' => config('site.version'),
            'user' => [
                'id' => (int) $user->id,
                'email' => (string) $user->email,
                'pending_email' => (string) ($user->pending_email ?? ''),
                'name' => (string) $user->name,
                'telefoon' => (string) ($user->telefoon ?? ''),
                'locatie_label' => (string) ($user->locatie_label ?? ''),
                'lat' => $user->lat !== null ? Geo::roundCoord((float) $user->lat) : null,
                'lon' => $user->lon !== null ? Geo::roundCoord((float) $user->lon) : null,
                'email_verified_at' => optional($user->email_verified_at)?->toIso8601String(),
                'created_at' => optional($user->created_at)?->toIso8601String(),
                'active' => (bool) $user->active,
            ],
            'aanbod' => $aanbod,
        ];

        if (Schema::hasTable('conversations')) {
            $convs = DB::table('conversations')
                ->where('user_a_id', $user->id)
                ->orWhere('user_b_id', $user->id)
                ->orderBy('id')
                ->get();
            $payload['conversations'] = $convs->map(fn ($c) => (array) $c)->all();
            $convIds = $convs->pluck('id')->all();

            $messages = [];
            if ($convIds !== [] && Schema::hasTable('messages')) {
                $messages = DB::table('messages')
                    ->whereIn('conversation_id', $convIds)
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn ($m) => (array) $m)
                    ->all();
            }
            $payload['messages'] = $messages;

            if (Schema::hasTable('blocks')) {
                $payload['blocks'] = DB::table('blocks')
                    ->where('blocker_id', $user->id)
                    ->orWhere('blocked_id', $user->id)
                    ->get()
                    ->map(fn ($b) => (array) $b)
                    ->all();
            }
            if (Schema::hasTable('reports')) {
                $payload['reports_filed'] = DB::table('reports')
                    ->where('reporter_id', $user->id)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
            }
        }

        $this->assertExportSafe($payload);

        return $payload;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function deleteUserAccount(User $user, #[\SensitiveParameter] string $password): array
    {
        if ($password === '' || ! Hash::check($password, $user->password)) {
            return ['ok' => false, 'error' => 'Wachtwoord klopt niet.'];
        }

        $userId = (int) $user->id;

        // Uitloggen vóór delete: Auth::logout() kan anders setRememberToken+save doen
        // en de zojuist verwijderde user weer wegschrijven.
        Auth::logout();
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        DB::transaction(function () use ($userId) {
            if (Schema::hasTable('conversations')) {
                $ids = DB::table('conversations')
                    ->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId)
                    ->pluck('id')
                    ->all();
                if ($ids !== [] && Schema::hasTable('messages')) {
                    DB::table('messages')->whereIn('conversation_id', $ids)->delete();
                }
                if ($ids !== []) {
                    DB::table('conversations')->whereIn('id', $ids)->delete();
                }
            }
            if (Schema::hasTable('blocks')) {
                DB::table('blocks')
                    ->where('blocker_id', $userId)
                    ->orWhere('blocked_id', $userId)
                    ->delete();
            }
            if (Schema::hasTable('reports')) {
                DB::table('reports')
                    ->where('reporter_id', $userId)
                    ->orWhere('reported_user_id', $userId)
                    ->delete();
            }
            Aanbod::query()->where('user_id', $userId)->delete();
            User::query()->whereKey($userId)->delete();
        });

        return ['ok' => true];
    }

    /** @param array<string, mixed> $payload */
    private function assertExportSafe(array $payload): void
    {
        $json = json_encode($payload);
        if ($json === false) {
            throw new \RuntimeException('Export mislukt.');
        }
        foreach (['password', 'password_hash', 'remember_token', 'renew_token'] as $needle) {
            if (str_contains($json, '"'.$needle.'"')) {
                throw new \RuntimeException('Export geblokkeerd.');
            }
        }
    }
}
