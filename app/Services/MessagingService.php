<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NewMessageMail;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Support\FormGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class MessagingService
{
    /**
     * @return array{ok: bool, error?: string, conversation?: Conversation}
     */
    public function startOrGet(int $viewerId, int $otherUserId, ?int $aanbodId): array
    {
        if ($viewerId === $otherUserId) {
            return ['ok' => false, 'error' => 'Je kunt jezelf geen bericht sturen.'];
        }
        if (Block::existsBetween($viewerId, $otherUserId)) {
            return ['ok' => false, 'error' => 'Berichten naar deze gebruiker zijn niet mogelijk.'];
        }
        if (! User::query()->whereKey($otherUserId)->where('active', true)->whereNotNull('email_verified_at')->exists()) {
            return ['ok' => false, 'error' => 'Gebruiker niet gevonden.'];
        }

        $a = min($viewerId, $otherUserId);
        $b = max($viewerId, $otherUserId);

        $existing = Conversation::query()
            ->where('user_a_id', $a)
            ->where('user_b_id', $b)
            ->when(
                $aanbodId === null,
                fn ($q) => $q->whereNull('aanbod_id'),
                fn ($q) => $q->where('aanbod_id', $aanbodId)
            )
            ->first();

        if ($existing) {
            return ['ok' => true, 'conversation' => $existing];
        }

        $conversation = Conversation::query()->create([
            'aanbod_id' => $aanbodId,
            'user_a_id' => $a,
            'user_b_id' => $b,
        ]);

        return ['ok' => true, 'conversation' => $conversation];
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array{ok: bool, error?: string}
     */
    public function sendMessage(int $senderId, Conversation $conversation, string $body, array $post, string $ip): array
    {
        if (FormGuard::honeypotFilled(isset($post['website']) ? (string) $post['website'] : null)) {
            return ['ok' => true];
        }
        if (FormGuard::submittedTooFast($post['form_started'] ?? null)) {
            return ['ok' => false, 'error' => 'Even geduld, het formulier ging te snel.'];
        }

        $key = 'message:'.$ip;
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return ['ok' => false, 'error' => 'Te veel berichten. Probeer later opnieuw.'];
        }

        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'Schrijf een bericht.'];
        }
        if (mb_strlen($body) > 4000) {
            return ['ok' => false, 'error' => 'Bericht is te lang.'];
        }
        if (! $conversation->involves($senderId)) {
            return ['ok' => false, 'error' => 'Geen toegang tot dit gesprek.'];
        }

        $otherId = $conversation->otherUserId($senderId);
        if (Block::existsBetween($senderId, $otherId)) {
            return ['ok' => false, 'error' => 'Je kunt deze gebruiker geen bericht sturen.'];
        }

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'body' => $body,
            'created_at' => now(),
        ]);
        RateLimiter::hit($key, 3600);

        $other = User::query()->find($otherId);
        if ($other && $other->email) {
            try {
                Mail::to($other->email)->send(new NewMessageMail);
            } catch (Throwable) {
                // Versturen mislukt mag het bericht zelf niet terugdraaien.
            }
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function setContactPreference(int $userId, Conversation $conversation, string $choice): array
    {
        if (! in_array($choice, ['ja', 'nee'], true)) {
            return ['ok' => false, 'error' => 'Kies ja of nee.'];
        }
        if (! $conversation->involves($userId)) {
            return ['ok' => false, 'error' => 'Geen toegang.'];
        }

        if ((int) $conversation->user_a_id === $userId) {
            $conversation->update(['contact_a' => $choice]);
        } else {
            $conversation->update(['contact_b' => $choice]);
        }

        return ['ok' => true];
    }

    /**
     * Markeer een overdracht als afgerond (eenmalig). Vereist wederzijds contact.
     *
     * @return array{ok: bool, error?: string, first?: bool}
     */
    public function completeOverdracht(int $userId, Conversation $conversation, MilestoneService $milestones): array
    {
        if (! $conversation->involves($userId)) {
            return ['ok' => false, 'error' => 'Geen toegang.'];
        }
        if (! $conversation->hasMutualContact()) {
            return ['ok' => false, 'error' => 'Rond eerst wederzijds contact af voordat je de overdracht markeert.'];
        }

        $conversation->refresh();
        if ($conversation->isOverdrachtAfgerond()) {
            return ['ok' => true, 'first' => false];
        }

        $conversation->update(['overdracht_at' => now()]);

        $userA = User::query()->find((int) $conversation->user_a_id);
        $userB = User::query()->find((int) $conversation->user_b_id);
        if ($userA) {
            $milestones->maybeSendEersteStekje($userA, 'eerste_overdracht');
        }
        if ($userB) {
            $milestones->maybeSendEersteStekje($userB, 'eerste_overdracht');
        }

        return ['ok' => true, 'first' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function report(int $reporterId, int $reportedId, ?int $conversationId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Schrijf kort waarom je meldt.'];
        }

        Report::query()->create([
            'reporter_id' => $reporterId,
            'reported_user_id' => $reportedId,
            'conversation_id' => $conversationId,
            'reason' => mb_substr($reason, 0, 1000),
            'handled' => false,
            'created_at' => now(),
        ]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function block(int $blockerId, int $blockedId): array
    {
        if ($blockerId === $blockedId) {
            return ['ok' => false, 'error' => 'Dat kan niet.'];
        }

        $exists = Block::query()
            ->where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->exists();

        if (! $exists) {
            DB::table('blocks')->insert([
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'created_at' => now(),
            ]);
        }

        return ['ok' => true];
    }

    /**
     * Privacy-safe view for the conversation page.
     *
     * @return array<string, mixed>|null
     */
    public function conversationView(int $viewerId, Conversation $conversation): ?array
    {
        if (! $conversation->involves($viewerId)) {
            return null;
        }

        $other = User::query()->find($conversation->otherUserId($viewerId));
        if (! $other) {
            return null;
        }

        $mutual = $conversation->hasMutualContact();
        $andere = [
            'id' => $other->id,
            'voornaam' => $other->name,
            'locatie_label' => (string) $other->locatie_label,
            'contact_gedeeld' => $mutual,
        ];
        if ($mutual) {
            $andere['email'] = $other->email;
            $tel = trim((string) $other->telefoon);
            if ($tel !== '') {
                $andere['telefoon'] = $tel;
            }
        }

        return [
            'id' => $conversation->id,
            'aanbod_id' => $conversation->aanbod_id,
            'mijn_contactkeuze' => $conversation->myContactChoice($viewerId),
            'wederzijds_contact' => $mutual,
            'overdracht_afgerond' => $conversation->isOverdrachtAfgerond(),
            'andere' => $andere,
            'veiligheidstips' => 'Spreek af op een plek die voor jou veilig voelt, bijvoorbeeld bij je voordeur of overdag op een openbare plek. Deel geen exact huisadres via de site totdat je elkaar vertrouwt.',
            'berichten' => $conversation->messages()->get()->map(fn (Message $m) => [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'body' => $m->body,
                'created_at' => $m->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i'),
            ])->all(),
        ];
    }
}
