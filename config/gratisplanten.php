<?php

declare(strict_types=1);

/**
 * Site-specifieke drempels voor publieke teaser-blokken en stadspagina’s.
 */
return [

    /**
     * Minimaal aantal actieve aanbiedingen mét foto in de relevante scope vóór
     * «Net aangeboden» (home) / «Actueel aanbod» (stad) getoond wordt.
     * Default 8: volle grid foto-kaartjes. GP_RECENT_AANBOD_MIN=1 = oud gedrag.
     */
    'recent_aanbod_min' => (int) env('GP_RECENT_AANBOD_MIN', 8),

    /**
     * Minimaal aantal actieve publieke aanbiedingen óf zichtbare deelplekken per
     * gemeente vóór een automatische stadspagina geïndexeerd wordt (sitemap,
     * overzichtslinks, robots index). Vaste steden uit steden.json blijven
     * bereikbaar onder de drempel (noindex). Default 1: elke plaats met echt
     * aanbod krijgt een pagina.
     */
    'stad_aanbod_min' => (int) env('GP_STAD_AANBOD_MIN', 1),

    /** Cache-TTL voor de stadscatalogus (seconden). */
    'stad_catalogus_ttl' => (int) env('GP_STAD_CATALOGUS_TTL', 3600),

    /** Straal (km) voor automatische stadspagina’s (kaart + nabij zoeken). */
    'auto_stad_straal_km' => (float) env('GP_AUTO_STAD_STRAAL_KM', 12),

    /**
     * Bekende schrijfwijzen → canonieke steden.json-slug.
     * Voorkomt dubbele pagina’s (Den Haag / ’s-Gravenhage).
     *
     * @var array<string, string>
     */
    'plaats_aliases' => [
        's-gravenhage' => 'den-haag',
        'gravenhage' => 'den-haag',
        's-hertogenbosch' => 'den-bosch',
        'hertogenbosch' => 'den-bosch',
        'den-bosch' => 'den-bosch',
    ],

];
