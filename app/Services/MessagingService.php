<?php

declare(strict_types=1);

namespace App\Services;

use App\Casts\SafeEncrypted;
use App\Mail\NewMessageMail;
use App\Models\Aanbod;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Support\FormGuard;
use App\Support\SafeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'body' => $body,
            'created_at' => now(),
        ]);
        RateLimiter::hit($key, 3600);

        try {
            $this->notifyRecipient($senderId, $otherId, $conversation, $message);
        } catch (Throwable) {
            // Melden mislukt mag het bericht zelf niet terugdraaien.
        }

        return ['ok' => true];
    }

    /** Na een eerste mail geen nieuwe mail zolang de ontvanger het gesprek niet opende (max. dit aantal uur). */
    public const BUNDEL_UREN = 24;

    /**
     * Mail de ontvanger over een nieuw bericht, tenzij die dat uitzette of al een
     * mail kreeg voor eerdere, nog ongelezen berichten in dit gesprek.
     */
    private function notifyRecipient(int $senderId, int $otherId, Conversation $conversation, Message $message): void
    {
        $other = User::query()->find($otherId);
        if (! $other || ! $other->email || $other->berichtmail === false) {
            return;
        }

        $eerderOngelezen = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', $senderId)
            ->whereKeyNot($message->getKey())
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subHours(self::BUNDEL_UREN))
            ->exists();
        if ($eerderOngelezen) {
            return;
        }

        $sender = User::query()->find($senderId);

        SafeMail::mailable($other->email, new NewMessageMail(
            afzender: $sender ? (string) $sender->name : '',
            onderwerp: $this->onderwerp($conversation),
            gesprekUrl: route('account.gesprek', $conversation),
            uitzettenUrl: URL::signedRoute('berichtmail.uit', ['user' => $other->id]),
        ), (int) $other->id);
    }

    /** Korte omschrijving van het aanbod waar het gesprek over gaat (openbare tekst). */
    private function onderwerp(Conversation $conversation): ?string
    {
        if ($conversation->aanbod_id === null) {
            return null;
        }
        $aanbod = Aanbod::query()->find($conversation->aanbod_id);
        if (! $aanbod) {
            return null;
        }

        $naam = null;
        if ($aanbod->plantensoort_id !== null) {
            $naam = app(Plantensoorten::class)->find((int) $aanbod->plantensoort_id)['naam'] ?? null;
        }
        if ($naam === null || $naam === '') {
            $tekst = trim((string) preg_replace('/\s+/', ' ', (string) $aanbod->tekst));
            $naam = mb_strlen($tekst) > 60 ? rtrim(mb_substr($tekst, 0, 57)).'…' : $tekst;
        }

        return $naam !== '' ? $naam : null;
    }

    /** Markeer berichten van de ander in dit gesprek als gelezen. */
    public function markRead(int $viewerId, Conversation $conversation): void
    {
        if (! $conversation->involves($viewerId)) {
            return;
        }

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $viewerId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** Aantal ongelezen berichten van anderen, over al je gesprekken. */
    public function unreadCount(int $userId): int
    {
        return Message::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $userId)
            ->whereIn('conversation_id', Conversation::query()
                ->select('id')
                ->where(fn ($q) => $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId)))
            ->count();
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
            if (! $other->isUnreadable('telefoon')) {
                $tel = trim((string) ($other->telefoon ?? ''));
                if ($tel !== '') {
                    $andere['telefoon'] = $tel;
                }
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
            'berichten' => $conversation->messages()->get()->map(function (Message $m) {
                $body = $m->isUnreadable('body')
                    ? SafeEncrypted::VIEW_FALLBACK
                    : (string) ($m->body ?? '');

                return [
                    'id' => $m->id,
                    'sender_id' => $m->sender_id,
                    'body' => $body,
                    'created_at' => $m->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i'),
                ];
            })->all(),
        ];
    }
}
