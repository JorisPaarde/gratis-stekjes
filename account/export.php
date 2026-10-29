<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();

$data = stekjes_export_user_data((int) $user['id']);
// Privacy-assert: export mag password_hash/tokens niet bevatten
try {
    stekjes_assert_no_sensitive([
        'user_keys' => array_keys($data['user']),
    ], true);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Export geblokkeerd.';
    exit;
}
if (isset($data['user']['password_hash']) || array_key_exists('password_hash', $data['user'])) {
    http_response_code(500);
    echo 'Export geblokkeerd.';
    exit;
}

$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="gegevens-export.json"');
header('Cache-Control: no-store');
echo $json;
exit;
