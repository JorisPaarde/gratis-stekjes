<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
stekjes_logout_user();
stekjes_flash_set('ok', 'Je bent uitgelogd.');
stekjes_redirect(stekjes_path('/'));
