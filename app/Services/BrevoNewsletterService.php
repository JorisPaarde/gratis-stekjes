<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RequestNewsletterDoiJob;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Nieuwsbrief via Brevo REST API (DOI + list sync). Geen persoonsgegevens in logs.
 * Lege config = uit (geen HTTP-calls).
 */
final class BrevoNewsletterService
{
    public const API_BASE = 'https://api.brevo.com/v3';

    public function configured(): bool
    {
        return $this->apiKey() !== ''
            && $this->listId() > 0
            && $this->doiTemplateId() > 0
            && $this->doiRedirectUrl() !== '';
    }

    public function apiKey(): string
    {
        return trim((string) config('services.brevo.api_key', ''));
    }

    public function listId(): int
    {
        return (int) config('services.brevo.newsletter_list_id', 0);
    }

    public function doiTemplateId(): int
    {
        return (int) config('services.brevo.doi_template_id', 0);
    }

    public function doiRedirectUrl(): string
    {
        return trim((string) config('services.brevo.doi_redirect_url', ''));
    }

    public function webhookToken(): string
    {
        return trim((string) config('services.brevo.webhook_token', ''));
    }

    /** Lokale opt-in aanzetten (registreren/profiel). DOI pas na e-mailbevestiging. */
    public function optInLocally(User $user): void
    {
        if (! Schema::hasColumn('users', 'newsletter_opt_in')) {
            return;
        }

        $user->forceFill([
            'newsletter_opt_in' => true,
            'newsletter_opt_in_at' => $user->newsletter_opt_in_at ?? now(),
        ])->save();
    }

    /**
     * Lokale opt-out + contact uit Brevo-lijst (fail-soft).
     * @return array{ok: bool, removed: bool}
     */
    public function optOut(User $user): array
    {
        if (Schema::hasColumn('users', 'newsletter_opt_in')) {
            $user->forceFill([
                'newsletter_opt_in' => false,
                'newsletter_listed_at' => null,
            ])->save();
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || ! $this->configured()) {
            return ['ok' => true, 'removed' => false];
        }

        $removed = $this->removeEmailFromList($email);

        return ['ok' => true, 'removed' => $removed];
    }

    /** Na eigen e-mailbevestiging: DOI-job inplannen (of direct als queue niet nodig). */
    public function queueDoubleOptIn(User $user): void
    {
        if (! Schema::hasColumn('users', 'newsletter_opt_in')) {
            return;
        }
        if (! $user->newsletter_opt_in || ! $user->hasVerifiedEmail()) {
            return;
        }
        if (! $this->configured()) {
            Log::info('brevo.doi_skipped', ['reason' => 'not_configured', 'user_id' => (int) $user->id]);

            return;
        }

        try {
            RequestNewsletterDoiJob::dispatch((int) $user->id);
        } catch (Throwable $e) {
            Log::error('brevo.doi_dispatch_failed', [
                'user_id' => (int) $user->id,
                'error' => SafeMail::safeSummary($e),
            ]);
            // Fail-soft: probeer synchroon als fallback.
            $this->sendDoubleOptIn($user);
        }
    }

    /**
     * POST /v3/contacts/doubleOptinConfirmation
     * @return array{ok: bool, status?: int, skipped?: bool}
     */
    public function sendDoubleOptIn(User $user): array
    {
        if (! $this->configured()) {
            return ['ok' => true, 'skipped' => true];
        }
        if (! $user->newsletter_opt_in || ! $user->hasVerifiedEmail()) {
            return ['ok' => true, 'skipped' => true];
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'status' => 0];
        }

        $payload = [
            'email' => $email,
            'includeListIds' => [$this->listId()],
            'templateId' => $this->doiTemplateId(),
            'redirectionUrl' => $this->doiRedirectUrl(),
        ];
        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            $payload['attributes'] = ['FIRSTNAME' => mb_substr($name, 0, 80)];
        }

        try {
            $response = $this->client()->post(self::API_BASE.'/contacts/doubleOptinConfirmation', $payload);
            $ok = $response->successful();
            if ($ok && Schema::hasColumn('users', 'newsletter_doi_sent_at')) {
                $user->forceFill(['newsletter_doi_sent_at' => now()])->save();
            }
            if (! $ok) {
                $this->logHttpFailure('doi', $response, (int) $user->id);
            }

            return ['ok' => $ok, 'status' => $response->status()];
        } catch (Throwable $e) {
            Log::error('brevo.doi_exception', [
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
     * Webhook: afmelden / contact verwijderd → lokale opt-in uit.
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, handled: int}
     */
    public function handleWebhook(array $payload): array
    {
        $events = $this->normalizeWebhookEvents($payload);
        $handled = 0;

        foreach ($events as $event) {
            $type = strtolower((string) ($event['event'] ?? $event['type'] ?? ''));
            $email = strtolower(trim((string) ($event['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (! $this->isUnsubscribeEvent($type)) {
                continue;
            }

            $user = User::query()->where('email', $email)->first();
            if ($user === null || ! Schema::hasColumn('users', 'newsletter_opt_in')) {
                continue;
            }

            $user->forceFill([
                'newsletter_opt_in' => false,
                'newsletter_listed_at' => null,
            ])->save();
            $handled++;
        }

        return ['ok' => true, 'handled' => $handled];
    }

    /**
     * Dagelijkse sync: lokale opt-in bijwerken + DOI opnieuw proberen.
     * @return array{ok: bool, listed: int, opted_out: int, doi_queued: int, removed: int, skipped?: bool}
     */
    public function syncFromBrevo(): array
    {
        if (! $this->configured() || ! Schema::hasColumn('users', 'newsletter_opt_in')) {
            return ['ok' => true, 'listed' => 0, 'opted_out' => 0, 'doi_queued' => 0, 'removed' => 0, 'skipped' => true];
        }

        $onList = $this->fetchListEmails();
        if ($onList === null) {
            return ['ok' => false, 'listed' => 0, 'opted_out' => 0, 'doi_queued' => 0, 'removed' => 0];
        }

        $listed = 0;
        $optedOut = 0;
        $doiQueued = 0;
        $removed = 0;

        User::query()
            ->where('newsletter_opt_in', true)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($onList, &$listed, &$optedOut, &$doiQueued): void {
                foreach ($users as $user) {
                    /** @var User $user */
                    $email = strtolower(trim((string) $user->email));
                    if ($email === '') {
                        continue;
                    }
                    if (isset($onList[$email])) {
                        if (Schema::hasColumn('users', 'newsletter_listed_at')) {
                            $user->forceFill(['newsletter_listed_at' => now()])->save();
                        }
                        $listed++;
                        continue;
                    }

                    // Was op de lijst, nu weg → afgemeld via Brevo.
                    if ($user->newsletter_listed_at !== null) {
                        $user->forceFill([
                            'newsletter_opt_in' => false,
                            'newsletter_listed_at' => null,
                        ])->save();
                        $optedOut++;
                        continue;
                    }

                    // Nog niet op de lijst: DOI opnieuw als bevestigd en (nog nooit / >1 dag geleden) verstuurd.
                    if ($user->hasVerifiedEmail()) {
                        $sentAt = $user->newsletter_doi_sent_at;
                        if ($sentAt === null || $sentAt->lt(now()->subDay())) {
                            $this->queueDoubleOptIn($user);
                            $doiQueued++;
                        }
                    }
                }
            });

        // Lokaal uit, nog wel op Brevo-lijst → uit de lijst.
        User::query()
            ->where('newsletter_opt_in', false)
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
            'opted_out' => $optedOut,
            'doi_queued' => $doiQueued,
            'removed' => $removed,
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

    private function isUnsubscribeEvent(string $type): bool
    {
        $type = str_replace(['-', ' '], '_', $type);

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
