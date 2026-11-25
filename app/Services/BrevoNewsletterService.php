<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SyncNewsletterContactJob;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Nieuwsbrief (opt-out) via Brevo REST API: alleen contacten/lijst synchroniseren.
 * Deze klasse verstuurt NOOIT een mail of campagne (geen /emailCampaigns, /smtp/email,
 * geen DOI-mail). Versturen doe je zelf in Brevo, met de verplichte afmeldlink.
 *
 * Bron van waarheid: users.newsletter_opt_in. Alleen bevestigde (email_verified_at) en
 * actieve leden komen op de Brevo-lijst. Geen persoonsgegevens in logs.
 *
 * Twee schakelaars: sync_enabled (BREVO_SYNC_ENABLED, standaard uit) + API-key/lijst-id.
 * Zonder sync_enabled doet de site lokaal alles (vlag, profiel, webhook) maar roept
 * Brevo nooit aan — zo raakt staging de echte lijst niet.
 */
final class BrevoNewsletterService
{
    public const API_BASE = 'https://api.brevo.com/v3';

    /** Max. nieuwe contacten per sync-run (cron-request kort houden). */
    public const MAX_ADDS_PER_RUN = 30;

    public function enabled(): bool
    {
        return filter_var(config('services.brevo.sync_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Mag de site Brevo aanroepen? (aan + sleutel + lijst-id) */
    public function configured(): bool
    {
        return $this->enabled()
            && $this->apiKey() !== ''
            && $this->listId() > 0;
    }

    public function apiKey(): string
    {
        return trim((string) config('services.brevo.api_key', ''));
    }

    public function listId(): int
    {
        return (int) config('services.brevo.newsletter_list_id', 0);
    }

    public function webhookToken(): string
    {
        return trim((string) config('services.brevo.webhook_token', ''));
    }

    private function hasColumns(): bool
    {
        return Schema::hasColumn('users', 'newsletter_opt_in');
    }

    /** Komt dit lid in aanmerking voor de Brevo-lijst? */
    public function eligible(User $user): bool
    {
        return (bool) $user->newsletter_opt_in
            && $user->hasVerifiedEmail()
            && $user->active !== false;
    }

    /** Lokale opt-in aanzetten (registreren/profiel). Wist een eerdere afmelding. */
    public function optInLocally(User $user): void
    {
        if (! $this->hasColumns()) {
            return;
        }

        $user->forceFill([
            'newsletter_opt_in' => true,
            'newsletter_opt_in_at' => now(),
            'newsletter_opt_out_at' => null,
        ])->save();
    }

    /**
     * Afmelden: lokale vlag uit (+ afmeldmoment) en contact van de Brevo-lijst (fail-soft).
     * @return array{ok: bool, removed: bool}
     */
    public function optOut(User $user): array
    {
        if ($this->hasColumns()) {
            $user->forceFill([
                'newsletter_opt_in' => false,
                'newsletter_opt_out_at' => now(),
                'newsletter_listed_at' => null,
            ])->save();
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || ! $this->configured()) {
            return ['ok' => true, 'removed' => false];
        }

        return ['ok' => true, 'removed' => $this->removeEmailFromList($email)];
    }

    /**
     * Contact (opnieuw) naar de lijst, op de achtergrond. $explicit = het lid zette het zojuist
     * zelf aan in het profiel: dan heft de sync een eerdere Brevo-blacklist van onze lijst op.
     */
    public function queueSync(User $user, bool $explicit = false): void
    {
        if (! $this->hasColumns() || ! $this->eligible($user)) {
            return;
        }
        if (! $this->configured()) {
            return;
        }

        try {
            SyncNewsletterContactJob::dispatch((int) $user->id, $explicit);
        } catch (Throwable $e) {
            Log::error('brevo.sync_dispatch_failed', [
                'user_id' => (int) $user->id,
                'error' => SafeMail::safeSummary($e),
            ]);
            $this->syncUser($user, $explicit);
        }
    }

    /**
     * Eén lid naar de lijst zetten (upsert, géén mail). Respecteert een afmelding in Brevo:
     * staat het contact geblacklist of uitgeschreven van onze lijst, dan gaat de lokale vlag uit
     * (tenzij $explicit).
     *
     * @return array{ok: bool, status?: int, skipped?: bool, respected?: bool}
     */
    public function syncUser(User $user, bool $explicit = false): array
    {
        if (! $this->configured() || ! $this->hasColumns() || ! $this->eligible($user)) {
            return ['ok' => true, 'skipped' => true];
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => true, 'skipped' => true];
        }

        try {
            $existing = $this->client()->get(self::API_BASE.'/contacts/'.rawurlencode($email));
            if ($existing->successful()) {
                $blacklisted = $existing->json('emailBlacklisted') === true;
                $unsubscribedHere = in_array($this->listId(), array_map('intval', (array) ($existing->json('listUnsubscribed') ?? [])), true);
                if (($blacklisted || $unsubscribedHere) && ! $explicit) {
                    $user->forceFill([
                        'newsletter_opt_in' => false,
                        'newsletter_opt_out_at' => now(),
                        'newsletter_listed_at' => null,
                    ])->save();

                    return ['ok' => true, 'respected' => true];
                }
            } elseif ($existing->status() !== 404) {
                $this->logHttpFailure('contact_get', $existing, (int) $user->id);

                return ['ok' => false, 'status' => $existing->status()];
            }

            $payload = [
                'email' => $email,
                'listIds' => [$this->listId()],
                'updateEnabled' => true,
                'attributes' => ['VOORNAAM' => (string) $user->name],
            ];
            if ($explicit) {
                $payload['emailBlacklisted'] = false;
            }

            $response = $this->client()->post(self::API_BASE.'/contacts', $payload);
            if (! $response->successful()) {
                $this->logHttpFailure('contact_upsert', $response, (int) $user->id);

                return ['ok' => false, 'status' => $response->status()];
            }

            $user->forceFill(['newsletter_listed_at' => now()])->save();

            return ['ok' => true, 'status' => $response->status()];
        } catch (Throwable $e) {
            Log::error('brevo.sync_user_exception', [
                'user_id' => (int) $user->id,
                'error' => SafeMail::safeSummary($e),
            ]);

            return ['ok' => false, 'status' => 0];
        }
    }

    /** Contact uit de nieuwsbrieflijst halen (geen PII in logs). */
    public function removeEmailFromList(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! $this->configured()) {
            return false;
        }

        try {
            $response = $this->client()->post(
                self::API_BASE.'/contacts/lists/'.$this->listId().'/contacts/remove',
                ['emails' => [$email]]
            );
            // 400 "Contact already removed" / niet gevonden: behandel als ok.
            if ($response->successful() || in_array($response->status(), [400, 404], true)) {
                return true;
            }
            $this->logHttpFailure('list_remove', $response, null);

            return false;
        } catch (Throwable $e) {
            Log::error('brevo.list_remove_exception', [
                'error' => SafeMail::safeSummary($e),
            ]);

            return false;
        }
    }

    /**
     * Webhook: afmelden / spam / contact verwijderd → lokale vlag uit. Werkt los van sync_enabled.
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, handled: int}
     */
    public function handleWebhook(array $payload): array
    {
        $events = $this->normalizeWebhookEvents($payload);
        $handled = 0;

        foreach ($events as $event) {
            $type = $this->normalizeEventType((string) ($event['event'] ?? $event['type'] ?? ''));
            $email = strtolower(trim((string) ($event['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (! $this->isUnsubscribeEvent($type)) {
                continue;
            }

            $user = User::query()->where('email', $email)->first();
            if ($user === null || ! $this->hasColumns()) {
                continue;
            }

            $user->forceFill([
                'newsletter_opt_in' => false,
                'newsletter_opt_out_at' => now(),
                'newsletter_listed_at' => null,
            ])->save();
            $handled++;
        }

        return ['ok' => true, 'handled' => $handled];
    }

    /**
     * Sync (cron): leden mét opt-in → lijst; afgemeld → van de lijst; in Brevo afgemeld → vlag uit.
     * Raakt alleen contacten van leden; andere contacten op de lijst blijven met rust.
     *
     * @return array{ok: bool, listed: int, added: int, opted_out: int, removed: int, pending: int, skipped?: bool}
     */
    public function sync(): array
    {
        $empty = ['ok' => true, 'listed' => 0, 'added' => 0, 'opted_out' => 0, 'removed' => 0, 'pending' => 0];
        if (! $this->configured() || ! $this->hasColumns()) {
            return $empty + ['skipped' => true];
        }

        $onList = $this->fetchListEmails();
        if ($onList === null) {
            return ['ok' => false] + $empty;
        }

        $listed = 0;
        $added = 0;
        $optedOut = 0;
        $removed = 0;
        $pending = 0;

        User::query()
            ->where('newsletter_opt_in', true)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($onList, &$listed, &$added, &$optedOut, &$pending): void {
                foreach ($users as $user) {
                    /** @var User $user */
                    if (! $this->eligible($user)) {
                        continue;
                    }
                    $email = strtolower(trim((string) $user->email));
                    if ($email === '') {
                        continue;
                    }
                    if (isset($onList[$email])) {
                        if ($user->newsletter_listed_at === null) {
                            $user->forceFill(['newsletter_listed_at' => now()])->save();
                        }
                        $listed++;
                        continue;
                    }

                    // Stond op de lijst, nu weg → afgemeld in Brevo.
                    if ($user->newsletter_listed_at !== null) {
                        $user->forceFill([
                            'newsletter_opt_in' => false,
                            'newsletter_opt_out_at' => now(),
                            'newsletter_listed_at' => null,
                        ])->save();
                        $optedOut++;
                        continue;
                    }

                    if ($added >= self::MAX_ADDS_PER_RUN) {
                        $pending++;
                        continue;
                    }
                    $result = $this->syncUser($user);
                    if (($result['respected'] ?? false) === true) {
                        $optedOut++;
                    } elseif (($result['ok'] ?? false) === true) {
                        $added++;
                    } else {
                        $pending++;
                    }
                }
            });

        // Lid niet (meer) in aanmerking, maar nog wel op de lijst → eraf.
        User::query()
            ->where(function ($q): void {
                $q->where('newsletter_opt_in', false)
                    ->orWhereNull('email_verified_at')
                    ->orWhere('active', false);
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($onList, &$removed): void {
                foreach ($users as $user) {
                    $email = strtolower(trim((string) $user->email));
                    if ($email !== '' && isset($onList[$email]) && $this->removeEmailFromList($email)) {
                        $removed++;
                    }
                }
            });

        return [
            'ok' => true,
            'listed' => $listed,
            'added' => $added,
            'opted_out' => $optedOut,
            'removed' => $removed,
            'pending' => $pending,
        ];
    }

    /**
     * @return array<string, true>|null  e-mail → true; null bij HTTP-fout
     */
    public function fetchListEmails(): ?array
    {
        if (! $this->configured()) {
            return [];
        }

        $emails = [];
        $offset = 0;
        $limit = 50;

        try {
            while (true) {
                $response = $this->client()->get(
                    self::API_BASE.'/contacts/lists/'.$this->listId().'/contacts',
                    ['limit' => $limit, 'offset' => $offset]
                );
                if (! $response->successful()) {
                    $this->logHttpFailure('list_fetch', $response, null);

                    return null;
                }
                $contacts = $response->json('contacts') ?? $response->json('list') ?? [];
                if (! is_array($contacts) || $contacts === []) {
                    break;
                }
                foreach ($contacts as $contact) {
                    $email = strtolower(trim((string) (is_array($contact) ? ($contact['email'] ?? '') : '')));
                    if ($email !== '') {
                        $emails[$email] = true;
                    }
                }
                if (count($contacts) < $limit) {
                    break;
                }
                $offset += $limit;
                if ($offset > 50000) {
                    break;
                }
            }
        } catch (Throwable $e) {
            Log::error('brevo.list_fetch_exception', [
                'error' => SafeMail::safeSummary($e),
            ]);

            return null;
        }

        return $emails;
    }

    /** @return \Illuminate\Http\Client\PendingRequest */
    private function client()
    {
        return Http::withHeaders([
            'api-key' => $this->apiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(20)->connectTimeout(5);
    }

    private function logHttpFailure(string $action, Response $response, ?int $userId): void
    {
        $body = mb_substr((string) preg_replace(
            '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
            '[email]',
            $response->body()
        ), 0, 200);

        Log::warning('brevo.http_failed', array_filter([
            'action' => $action,
            'status' => $response->status(),
            'user_id' => $userId,
            'body' => $body,
        ], static fn ($v) => $v !== null));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeWebhookEvents(array $payload): array
    {
        // Brevo stuurt soms één object, soms een lijst.
        if (array_is_list($payload)) {
            /** @var list<array<string, mixed>> $payload */
            return array_values(array_filter($payload, 'is_array'));
        }
        if (isset($payload[0]) && is_array($payload[0])) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [$payload];
    }

    /**
     * Brevo stuurt o.a. camelCase (`contactDeleted`, `unsubscribed`).
     * Zet camelCase/kebab/spaties om naar snake_case vóór matchen.
     */
    private function normalizeEventType(string $type): string
    {
        $type = trim($type);
        if ($type === '') {
            return '';
        }
        // contactDeleted → contact_Deleted, daarna lowercase.
        $type = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $type);
        $type = strtolower($type);
        $type = str_replace(['-', ' '], '_', $type);

        return (string) preg_replace('/_+/', '_', $type);
    }

    private function isUnsubscribeEvent(string $type): bool
    {
        // Al genormaliseerd naar snake_case; expliciet ook Brevo-namen
        // (unsubscribed, contactDeleted → contact_deleted, spam).
        return in_array($type, [
            'unsubscribe',
            'unsubscribed',
            'list_unsubscription',
            'contact_deleted',
            'contact_deleted_hardbounce',
            'spam',
        ], true);
    }
}
