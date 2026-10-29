<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$payload = stekjes_nu_te_stekken_nu();
stekjes_json_response([
    'ok' => true,
    'maand' => $payload['maand'],
    'maand_naam' => $payload['maand_naam'],
    'timezone' => $payload['timezone'],
    'titel' => 'Nu te stekken',
    'intro' => 'Wat past bij ' . $payload['maand_naam'] . ' (tijdzone ' . $payload['timezone'] . '). Tips zijn algemeen; check altijd je eigen plant en weer.',
    'groepen' => $payload['groepen'],
    'stektips_index' => stekjes_path('/stektips/'),
]);
