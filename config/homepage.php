<?php

declare(strict_types=1);

/**
 * Homepage-v2 copy. Wissel van hero-variant met `hero.active`
 * (a|b|c|d) — geen Blade-wijziging nodig.
 *
 * Actief: B (H1 + tagline, account als primaire CTA).
 */
return [

    'hero' => [
        /** Actieve variant: a | b | c | d */
        'active' => env('HOMEPAGE_HERO_VARIANT', 'b'),

        'variants' => [
            'a' => [
                'heading' => 'Gratis planten, meer groen, meer contact',
                'tagline' => null,
                'lead' => 'Geef stekjes, zaden en planten weg aan mensen bij jou in de buurt, in Nederland en België. Zo komt er meer groen bij en leer je je buren kennen.',
                'primary' => [
                    'label' => 'Bekijk wat er bij jou te halen is',
                    'route' => 'aanbod',
                ],
                'secondary' => [
                    'label' => 'Maak gratis een account',
                    'route' => 'account.registreren',
                ],
            ],
            'b' => [
                'heading' => 'Gratis planten, stekjes en zaden bij jou in de buurt',
                'tagline' => 'Meer groen, meer contact',
                'lead' => null,
                'primary' => [
                    'label' => 'Maak gratis een account',
                    'route' => 'account.registreren',
                ],
                'secondary' => [
                    'label' => 'Open de kaart',
                    'route' => 'aanbod',
                ],
            ],
            'c' => [
                'heading' => 'Deel planten, stekjes en zaden met je buren',
                'tagline' => 'Gratis ophalen, een praatje als dank',
                'lead' => null,
                'primary' => [
                    'label' => 'Maak gratis een account',
                    'route' => 'account.registreren',
                ],
                'secondary' => [
                    'label' => 'Open de kaart',
                    'route' => 'aanbod',
                ],
            ],
            'd' => [
                'heading' => 'Meer groen bij jou om de hoek',
                'tagline' => 'Planten, stekjes en zaden delen in je buurt',
                'lead' => null,
                'primary' => [
                    'label' => 'Maak gratis een account',
                    'route' => 'account.registreren',
                ],
                'secondary' => [
                    'label' => 'Open de kaart',
                    'route' => 'aanbod',
                ],
            ],
        ],
    ],

    'meta' => [
        'title' => 'Gratis planten, stekjes en zaden in je buurt',
        'description' => 'Deel gratis planten, stekjes en zaden met mensen bij jou in de buurt in Nederland en België. Maak een account, open de kaart en haal groen op — of bied zelf iets aan.',
    ],

    /** Soort-slugs voor het vermeerdertips-blok (met bestaande foto’s). */
    'tips_slugs' => ['munt', 'lavendel', 'hortensia', 'rozemarijn', 'aardbei', 'monstera'],

    /** Max. organisaties in het blok op de homepage. */
    'organisaties_limit' => 6,

];
