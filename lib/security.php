<?php

declare(strict_types=1);

function stekjes_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function stekjes_ip_hash(?string $ip = null): string
{
    $ip = $ip ?? stekjes_client_ip();
    $salt = (string) (stekjes_config()['site_url'] ?? 'stekjes');
    return hash('sha256', $salt . '|' . $ip);
}

function stekjes_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // CLI/tests: geen cookies/headers
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return;
    }
    $config = stekjes_config();
    $secure = !empty($config['session_secure']);
    session_name((string) ($config['session_name'] ?? 'stekjes_sess'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function stekjes_csrf_token(): string
{
    stekjes_start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function stekjes_csrf_field(): string
{
    $t = htmlspecialchars(stekjes_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<input type="hidden" name="csrf" value="' . $t . '">';
}

function stekjes_verify_csrf(?string $token): bool
{
    stekjes_start_session();
    $expected = $_SESSION['csrf_token'] ?? '';
    return is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

function stekjes_clean(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    return substr($value, 0, $maxLength);
}

function stekjes_honeypot_filled(array $post): bool
{
    $hp = trim((string) ($post['website'] ?? $post['company_url'] ?? ''));
    return $hp !== '';
}

function stekjes_form_too_fast(array $post, ?int $minSeconds = null): bool
{
    $config = stekjes_config();
    $min = $minSeconds ?? (int) ($config['form_min_seconds'] ?? 3);
    $started = (int) ($post['form_started'] ?? 0);
    if ($started <= 0) {
        return true;
    }
    return (time() - $started) < $min;
}

function stekjes_rate_limit_hit(string $action): bool
{
    $config = stekjes_config();
    $rules = $config['rate_limit'][$action] ?? ['max' => 30, 'window' => 3600];
    $max = (int) $rules['max'];
    $window = (int) $rules['window'];
    $hash = stekjes_ip_hash();
    $pdo = stekjes_db();
    $since = time() - $window;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE ip_hash = ? AND action = ? AND created_at >= ?');
    $stmt->execute([$hash, $action, $since]);
    $count = (int) $stmt->fetchColumn();
    return $count >= $max;
}

function stekjes_rate_limit_record(string $action): void
{
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('INSERT INTO rate_limits (ip_hash, action, created_at) VALUES (?, ?, ?)');
    $stmt->execute([stekjes_ip_hash(), $action, time()]);
    // opruimen oud
    $pdo->prepare('DELETE FROM rate_limits WHERE created_at < ?')->execute([time() - 86400 * 2]);
}

function stekjes_random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function stekjes_now(): string
{
    return gmdate('c');
}

function stekjes_json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function stekjes_redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

function stekjes_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
