<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    stekjes_json_response(['error' => 'Alleen GET'], 405);
}

$type = stekjes_clean((string) ($_GET['type'] ?? ''), 32);
if ($type === '') {
    $type = null;
} elseif (!stekjes_partner_type_valid($type)) {
    stekjes_json_response(['error' => 'Onbekend type.'], 400);
}

$items = stekjes_list_partner_markers($type);
$payload = [
    'types' => stekjes_partner_type_labels(),
    'items' => $items,
];

try {
    stekjes_assert_no_sensitive($payload, false);
} catch (Throwable $e) {
    stekjes_json_response(['error' => 'Interne privacyfout'], 500);
}

stekjes_json_response($payload);
