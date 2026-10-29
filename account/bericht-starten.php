<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$user = stekjes_require_user();

$other = (int) ($_GET['user_id'] ?? 0);
$aanbodId = isset($_GET['aanbod_id']) ? (int) $_GET['aanbod_id'] : null;
if ($aanbodId === 0) {
    $aanbodId = null;
}

$result = stekjes_start_or_get_conversation((int) $user['id'], $other, $aanbodId);
if (empty($result['ok'])) {
    stekjes_flash_set('error', $result['error'] ?? 'Kon gesprek niet starten.');
    stekjes_redirect(stekjes_path('/aanbod/'));
}
stekjes_redirect(stekjes_path('/account/gesprek.php?id=' . (int) $result['conversation_id']));
