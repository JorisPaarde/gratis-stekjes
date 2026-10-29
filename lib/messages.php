<?php

declare(strict_types=1);

/** Veiligheidstips (placeholder; Joris vult later aan). */
function stekjes_safety_tips(): string
{
    return "Spreek af op een plek die voor jou veilig voelt, bijvoorbeeld bij je voordeur of overdag op een openbare plek. Deel geen exact huisadres via de site totdat je elkaar vertrouwt. Volgt binnenkort: meer tips.";
}

/**
 * @return array{ok:bool,error?:string,conversation_id?:int}
 */
function stekjes_start_or_get_conversation(int $viewerId, int $otherUserId, ?int $aanbodId): array
{
    if ($viewerId === $otherUserId) {
        return ['ok' => false, 'error' => 'Je kunt jezelf geen bericht sturen.'];
    }
    if (stekjes_is_blocked($viewerId, $otherUserId)) {
        return ['ok' => false, 'error' => 'Berichten naar deze gebruiker zijn niet mogelijk.'];
    }

    $a = min($viewerId, $otherUserId);
    $b = max($viewerId, $otherUserId);
    $pdo = stekjes_db();

    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE user_a_id = ? AND user_b_id = ? AND ((? IS NULL AND aanbod_id IS NULL) OR aanbod_id = ?)');
    $stmt->execute([$a, $b, $aanbodId, $aanbodId]);
    $existing = $stmt->fetch();
    if ($existing) {
        return ['ok' => true, 'conversation_id' => (int) $existing['id']];
    }

    $stmt = $pdo->prepare('INSERT INTO conversations (aanbod_id, user_a_id, user_b_id, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$aanbodId, $a, $b, stekjes_now()]);
    return ['ok' => true, 'conversation_id' => (int) $pdo->lastInsertId()];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_send_message(int $senderId, int $conversationId, string $body, array $post): array
{
    if (stekjes_honeypot_filled($post)) {
        return ['ok' => true];
    }
    if (stekjes_form_too_fast($post)) {
        return ['ok' => false, 'error' => 'Even geduld, het formulier ging te snel.'];
    }
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    if (stekjes_rate_limit_hit('message')) {
        return ['ok' => false, 'error' => 'Te veel berichten. Probeer later opnieuw.'];
    }

    $body = stekjes_clean($body, 4000);
    if ($body === '') {
        return ['ok' => false, 'error' => 'Schrijf een bericht.'];
    }

    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch();
    if (!$conv) {
        return ['ok' => false, 'error' => 'Gesprek niet gevonden.'];
    }
    $a = (int) $conv['user_a_id'];
    $b = (int) $conv['user_b_id'];
    if ($senderId !== $a && $senderId !== $b) {
        return ['ok' => false, 'error' => 'Geen toegang tot dit gesprek.'];
    }
    $otherId = $senderId === $a ? $b : $a;
    if (stekjes_is_blocked($senderId, $otherId)) {
        return ['ok' => false, 'error' => 'Je kunt deze gebruiker geen bericht sturen.'];
    }
    // Blokkeer: geblokkeerde kan geen berichten meer sturen
    $blockedByOther = $pdo->prepare('SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockedByOther->execute([$otherId, $senderId]);
    if ($blockedByOther->fetchColumn()) {
        return ['ok' => false, 'error' => 'Je kunt deze gebruiker geen bericht sturen.'];
    }

    $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$conversationId, $senderId, $body, stekjes_now()]);
    stekjes_rate_limit_record('message');

    $other = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $other->execute([$otherId]);
    $otherRow = $other->fetch();
    if ($otherRow) {
        stekjes_mail_new_message((string) $otherRow['email']);
    }
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_set_contact_preference(int $userId, int $conversationId, string $choice, array $post): array
{
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    $choice = stekjes_clean($choice, 10);
    if (!in_array($choice, ['ja', 'nee'], true)) {
        return ['ok' => false, 'error' => 'Kies ja of nee.'];
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch();
    if (!$conv) {
        return ['ok' => false, 'error' => 'Gesprek niet gevonden.'];
    }
    $a = (int) $conv['user_a_id'];
    $b = (int) $conv['user_b_id'];
    if ($userId === $a) {
        $pdo->prepare('UPDATE conversations SET contact_a = ? WHERE id = ?')->execute([$choice, $conversationId]);
    } elseif ($userId === $b) {
        $pdo->prepare('UPDATE conversations SET contact_b = ? WHERE id = ?')->execute([$choice, $conversationId]);
    } else {
        return ['ok' => false, 'error' => 'Geen toegang.'];
    }
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_report_user(int $reporterId, int $reportedId, ?int $conversationId, string $reason, array $post): array
{
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    $reason = stekjes_clean($reason, 1000);
    if ($reason === '') {
        return ['ok' => false, 'error' => 'Schrijf kort waarom je meldt.'];
    }
    stekjes_db()->prepare(
        'INSERT INTO reports (reporter_id, reported_user_id, conversation_id, reason, created_at) VALUES (?, ?, ?, ?, ?)'
    )->execute([$reporterId, $reportedId, $conversationId, $reason, stekjes_now()]);
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_block_user(int $blockerId, int $blockedId, array $post): array
{
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    if ($blockerId === $blockedId) {
        return ['ok' => false, 'error' => 'Dat kan niet.'];
    }
    stekjes_db()->prepare('INSERT OR IGNORE INTO blocks (blocker_id, blocked_id, created_at) VALUES (?, ?, ?)')
        ->execute([$blockerId, $blockedId, stekjes_now()]);
    return ['ok' => true];
}

/**
 * Gespreksweergave voor viewer: privacy-safe.
 * @return array<string,mixed>|null
 */
function stekjes_conversation_view(int $viewerId, int $conversationId): ?array
{
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch();
    if (!$conv) {
        return null;
    }
    $a = (int) $conv['user_a_id'];
    $b = (int) $conv['user_b_id'];
    if ($viewerId !== $a && $viewerId !== $b) {
        return null;
    }
    $otherId = $viewerId === $a ? $b : $a;
    $myChoice = $viewerId === $a ? ($conv['contact_a'] ?? null) : ($conv['contact_b'] ?? null);

    $otherStmt = $pdo->prepare('SELECT id, email, voornaam, telefoon, locatie_label FROM users WHERE id = ?');
    $otherStmt->execute([$otherId]);
    $other = $otherStmt->fetch();
    if (!$other) {
        return null;
    }

    $msgStmt = $pdo->prepare('SELECT id, sender_id, body, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC');
    $msgStmt->execute([$conversationId]);
    $messages = $msgStmt->fetchAll();

    return [
        'id' => (int) $conv['id'],
        'aanbod_id' => $conv['aanbod_id'] !== null ? (int) $conv['aanbod_id'] : null,
        'mijn_contactkeuze' => $myChoice,
        'wederzijds_contact' => stekjes_mutual_contact($conv, $viewerId),
        'andere' => stekjes_other_user_view($other, $conv, $viewerId),
        'veiligheidstips' => stekjes_safety_tips(),
        'berichten' => array_map(static function ($m) {
            return [
                'id' => (int) $m['id'],
                'sender_id' => (int) $m['sender_id'],
                'body' => (string) $m['body'],
                'created_at' => (string) $m['created_at'],
            ];
        }, $messages),
    ];
}
