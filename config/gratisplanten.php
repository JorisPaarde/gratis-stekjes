<?php

declare(strict_types=1);

/**
 * Site-specifieke drempels voor publieke teaser-blokken.
 */
return [

    /**
     * Minimaal aantal actieve aanbiedingen mét foto in de relevante scope vóór
     * «Net aangeboden» (home) / «Actueel aanbod» (stad) getoond wordt.
     * Default 8: volle grid foto-kaartjes. GP_RECENT_AANBOD_MIN=1 = oud gedrag.
     */
    'recent_aanbod_min' => (int) env('GP_RECENT_AANBOD_MIN', 8),

];
