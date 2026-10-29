<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$token = stekjes_clean((string) ($_GET['token'] ?? ''), 128);
$result = stekjes_confirm_email($token);
if (!empty($result['ok'])) {
    stekjes_flash_set('ok', 'E-mail bevestigd. Vul je profiel aan om aanbod te plaatsen.');
    stekjes_redirect(stekjes_path('/account/profiel.php'));
}
stekjes_flash_set('error', $result['error'] ?? 'Bevestigen mislukt.');
stekjes_redirect(stekjes_path('/account/inloggen.php'));
