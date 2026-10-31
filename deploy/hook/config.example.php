<?php

/**
 * Voorbeeld — NIET committen met echte token_hash.
 * Plaats als /home/gratisplanten/.deploy/staging.php (mode 0600),
 * map /home/gratisplanten/.deploy/ mode 0700.
 *
 * Genereer veilig via: ./scripts/make-deploy-hook-config.sh staging
 */
return [
    // password_hash('…', PASSWORD_DEFAULT) of hash('sha256', $token)
    'token_hash' => '$2y$10$REPLACE_WITH_PASSWORD_HASH_OR_SHA256_HEX',
    'app_dir' => '/home/gratisplanten/app-staging',
    'docroot' => '/home/gratisplanten/staging.gratisplanten.nl',
    'env' => 'staging',
    // Optioneel: account-home voor LiteSpeed-marker ~/.lsphp_restart.txt
    // (default: dirname(app_dir)). Hook herschrijft de inhoud ná de swap — geen touch-only.
    // 'home_dir' => '/home/gratisplanten',
];
