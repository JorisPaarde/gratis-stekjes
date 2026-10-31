<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\AanbodFoto;
use App\Models\Conversation;
use App\Models\Ervaring;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\MilestoneSent;
use App\Models\Report;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class AvgService
{
    /**
     * Export gebruikersgegevens (geen password/tokens). Aanbodfoto’s als media-URL’s; ervaring-foto’s als relatieve paden.
     *
     * @return array<string, mixed>
     */
    public function exportUserData(User $user): array
    {
        $aanbodRows = Aanbod::query()
            ->where('user_id', $user->id)
            ->with('fotos')
            ->orderBy('id')
            ->get();

        $aanbod = $aanbodRows->map(static function (Aanbod $a): array {
            return [
                'id' => $a->id,
                'type' => $a->type,
                'tekst' => $a->tekst,
                'visible' => $a->visible,
                'created_at' => $a->created_at,
                'updated_at' => $a->updated_at,
                'expires_at' => $a->expires_at,
            ];
        })->toArray();

        $fotos = [];
        foreach ($aanbodRows as $row) {
            foreach ($row->fotos as $foto) {
                /** @var AanbodFoto $foto */
                $fotos[] = [
                    'id' => $foto->id,
                    'aanbod_id' => $foto->aanbod_id,
                    'url' => $foto->publicUrl(),
                    'thumb_url' => $foto->thumbUrl(),
                    'alt' => $foto->toPublicArray()['alt'],
                    'width' => $foto->width,
                    'height' => $foto->height,
                    'created_at' => optional($foto->created_at)?->toIso8601String(),
                ];
            }
        }

        $ervaringen = [];
        if (Schema::hasTable('ervaringen')) {
            $ervaringen = Ervaring::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get()
                ->map(function (Ervaring $e) use (&$fotos) {
                    if ($e->hasPhoto()) {
                        $fotos[] = [
                            'ervaring_id' => $e->id,
                            'path' => (string) $e->photo_path,
                        ];
                    }

                    return [
                        'id' => $e->id,
                        'body' => (string) $e->body,
                        'plaatsnaam' => (string) $e->plaatsnaam,
                        'plantensoort_id' => $e->plantensoort_id,
                        'aanbod_id' => $e->aanbod_id,
                        'status' => (string) $e->status,
                        'photo_path' => $e->photo_path,
                        'approved_at' => optional($e->approved_at)?->toIso8601String(),
                        'created_at' => optional($e->created_at)?->toIso8601String(),
                    ];
                })
                ->all();
        }

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
            'ervaringen' => $ervaringen,
            'fotos' => $fotos,
        ];

        if (Schema::hasTable('conversations')) {
            $convs = Conversation::query()
                ->where('user_a_id', $user->id)
                ->orWhere('user_b_id', $user->id)
                ->orderBy('id')
                ->get();
            $payload['conversations'] = $convs->map(fn (Conversation $c) => [
                'id' => $c->id,
                'aanbod_id' => $c->aanbod_id,
                'user_a_id' => $c->user_a_id,
                'user_b_id' => $c->user_b_id,
                'contact_a' => $c->contact_a,
                'contact_b' => $c->contact_b,
                'created_at' => optional($c->created_at)?->toIso8601String(),
                'updated_at' => optional($c->updated_at)?->toIso8601String(),
            ])->all();
            $convIds = $convs->pluck('id')->all();

            $messages = [];
            if ($convIds !== [] && Schema::hasTable('messages')) {
                $messages = Message::query()
                    ->whereIn('conversation_id', $convIds)
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn (Message $m) => [
                        'id' => $m->id,
                        'conversation_id' => $m->conversation_id,
                        'sender_id' => $m->sender_id,
                        'body' => (string) $m->body,
                        'read_at' => optional($m->read_at)?->toIso8601String(),
                        'created_at' => optional($m->created_at)?->toIso8601String(),
                    ])
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
                $payload['reports_filed'] = Report::query()
                    ->where('reporter_id', $user->id)
                    ->get()
                    ->map(fn (Report $r) => [
                        'id' => $r->id,
                        'reporter_id' => $r->reporter_id,
                        'reported_user_id' => $r->reported_user_id,
                        'conversation_id' => $r->conversation_id,
                        'reason' => (string) $r->reason,
                        'handled' => (bool) $r->handled,
                        'created_at' => optional($r->created_at)?->toIso8601String(),
                    ])
                    ->all();
            }
        }

        if (Schema::hasTable('feedback')) {
            $payload['feedback'] = Feedback::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Feedback $f) => [
                    'id' => $f->id,
                    'page_path' => (string) $f->page_path,
                    'body' => (string) $f->body,
                    'read_at' => optional($f->read_at)?->toIso8601String(),
                    'created_at' => optional($f->created_at)?->toIso8601String(),
                ])
                ->all();
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
        $email = (string) $user->email;

        // Uitloggen vóór delete: Auth::logout() kan anders setRememberToken+save doen
        // en de zojuist verwijderde user weer wegschrijven.
        Auth::logout();
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        // Foto’s op disk vóór DB-cascade wissen (AVG).
        app(AanbodFotoService::class)->deleteAllForUser($userId);

        DB::transaction(function () use ($userId, $email) {
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

            if (Schema::hasTable('ervaringen')) {
                $photoPaths = Ervaring::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('photo_path')
                    ->pluck('photo_path')
                    ->all();
                Ervaring::query()->where('user_id', $userId)->delete();
                foreach ($photoPaths as $path) {
                    $path = trim((string) $path);
                    if ($path === '') {
                        continue;
                    }
                    try {
                        Storage::disk('public')->delete($path);
                    } catch (\Throwable) {
                        // Best-effort; AVG-wissen mag niet crashen.
                    }
                }
            }

            if (Schema::hasTable('milestones_sent')) {
                MilestoneSent::query()->where('user_id', $userId)->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $userId)->delete();
            }

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
