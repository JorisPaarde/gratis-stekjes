<?php

declare(strict_types=1);

/**
 * Privacy-helpers: publieke/andere-gebruiker views strippen gevoelige velden.
 */

const STEKJES_SENSITIVE_KEYS = [
    'email', 'password_hash', 'telefoon', 'confirm_token', 'reset_token',
    'reset_expires', 'smtp_pass', 'password', 'phone',
];

/**
 * Publiek aanbod-item voor zoek-API / kaart.
 * @param array<string,mixed> $row join van aanbod + users
 * @return array<string,mixed>
 */
function stekjes_public_aanbod_item(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'type' => (string) $row['type'],
        'tekst' => (string) $row['tekst'],
        'voornaam' => (string) $row['voornaam'],
        'locatie_label' => (string) ($row['locatie_label'] ?? ''),
        'lat' => isset($row['lat']) && $row['lat'] !== null ? stekjes_round_coord((float) $row['lat']) : null,
        'lon' => isset($row['lon']) && $row['lon'] !== null ? stekjes_round_coord((float) $row['lon']) : null,
        'user_id' => (int) $row['user_id'],
    ];
}

function stekjes_mutual_contact(array $conversation, int $viewerId): bool
{
    $a = $conversation['contact_a'] ?? null;
    $b = $conversation['contact_b'] ?? null;
    return $a === 'ja' && $b === 'ja';
}

/**
 * Ander-profiel in een gesprek: contactgegevens alleen bij wederzijds ja.
 * @return array<string,mixed>
 */
function stekjes_other_user_view(array $other, array $conversation, int $viewerId): array
{
    $view = [
        'id' => (int) $other['id'],
        'voornaam' => (string) $other['voornaam'],
        'locatie_label' => (string) ($other['locatie_label'] ?? ''),
    ];
    if (stekjes_mutual_contact($conversation, $viewerId)) {
        $view['email'] = (string) $other['email'];
        $tel = trim((string) ($other['telefoon'] ?? ''));
        if ($tel !== '') {
            $view['telefoon'] = $tel;
        }
        $view['contact_gedeeld'] = true;
    } else {
        $view['contact_gedeeld'] = false;
    }
    return $view;
}

/**
 * Controleer dat een array (recursief) geen verboden sleutels/waarden lekt
 * tenzij $allowContact true is.
 */
function stekjes_assert_no_sensitive(array $data, bool $allowContact = false): void
{
    $walk = static function ($node, string $path) use (&$walk, $allowContact): void {
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $k => $v) {
            $key = is_string($k) ? strtolower($k) : (string) $k;
            $p = $path . '.' . $key;
            if (!$allowContact && in_array($key, STEKJES_SENSITIVE_KEYS, true)) {
                throw new RuntimeException("Gevoelig veld gelekt: $p");
            }
            // Exacte coordinaten met > 3 decimalen verboden in publieke output
            if (($key === 'lat' || $key === 'lon') && is_numeric($v)) {
                $s = rtrim(rtrim(sprintf('%.10F', (float) $v), '0'), '.');
                if (preg_match('/\.\d{4,}$/', $s)) {
                    throw new RuntimeException("Te precieze coördinaat: $p");
                }
            }
            if (is_array($v)) {
                $walk($v, $p);
            } elseif (!$allowContact && is_string($v) && str_contains($v, '@') && preg_match('/\b\S+@\S+\.\S+\b/', $v)) {
                // e-mail in vrije tekst van API-structuurvelden weigeren (niet in gebruikersbericht-inhoud onder 'body')
                if (!in_array($key, ['body', 'tekst', 'reason', 'error', 'message'], true)) {
                    throw new RuntimeException("E-mail in veld: $p");
                }
            }
        }
    };
    $walk($data, 'root');
}

function stekjes_is_blocked(int $a, int $b): bool
{
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT 1 FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1');
    $stmt->execute([$a, $b, $b, $a]);
    return (bool) $stmt->fetchColumn();
}
