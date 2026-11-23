<?php

declare(strict_types=1);

/**
 * Publieke SEO-overrides (geen DB nodig). Keys = canonieke slugs.
 *
 * organisaties: seo_title + seo_description voor /organisaties/{slug}
 *   (og:title / og:description volgen via de layout uit dezelfde velden).
 *
 * stad_related_organisatie: interne link vanaf /gratis-planten/{stad}
 *   naar een organisatiepagina (anchor + korte intro).
 */
return [

    'organisaties' => [
        'plantenasiel-haarlem' => [
            'seo_title' => 'Plantenasiel Haarlem: planten brengen of ophalen | Gratis planten',
            'seo_description' => 'Bij Plantenasiel Haarlem kun je planten brengen of ophalen. Dinsdagavond open in wijkcentrum Alleman — een tweede kans voor planten in Haarlem.',
        ],
        'plantenasiel-leiden' => [
            'seo_title' => 'Plantenasiel Leiden: planten brengen of ophalen | Gratis planten',
            'seo_description' => 'Bij Plantenasiel Leiden kun je planten brengen of ophalen. Broedplaats Hazewind is een vaste plek in Leiden voor planten die een nieuw thuis zoeken.',
        ],
    ],

    'stad_related_organisatie' => [
        'leiden' => [
            'organisatie_slug' => 'plantenasiel-leiden',
            'anchor' => 'Plantenasiel Leiden: planten brengen of ophalen',
            'intro' => 'Ik ken in Leiden ook een plantenasiel waar je planten kunt brengen of ophalen.',
        ],
        'utrecht' => [
            'organisatie_slug' => 'plantenhuis-utrecht',
            'anchor' => 'Het Plantenhuis (Plantenasiel Utrecht): planten brengen of ophalen',
            'intro' => 'Op zoek naar een plantenasiel in Utrecht? Bij Het Plantenhuis kun je kamerplanten brengen of ophalen.',
        ],
    ],

];
