<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$t = stekjes_meedoen_teller();
stekjes_json_response([
    'show' => $t['show'],
    'count' => $t['show'] ? $t['count'] : null,
    'threshold' => $t['threshold'],
]);
