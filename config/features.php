<?php

declare(strict_types=1);

/**
 * Feature-vlaggen. Defaults zijn veilig voor productie (uit).
 * Staging zet HOMEPAGE_V2=true via de deploy-.env.
 */
return [

    /**
     * Nieuwe homepage (beeldrijker, bezoekerstaal).
     * Default uit — prod toont de huidige homepage tot Joris ja zegt.
     * Staging: HOMEPAGE_V2=true in deploy-hook-upload.sh.
     */
    'homepage_v2' => filter_var(env('HOMEPAGE_V2', false), FILTER_VALIDATE_BOOL),

];
