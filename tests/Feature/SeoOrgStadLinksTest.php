<?php

declare(strict_types=1);

use App\Support\SeoOverrides;
use App\Support\SeoTitle;

test('plantenasiel Haarlem en Leiden hebben SEO title en meta uit config', function () {
    $cases = [
        'plantenasiel-haarlem' => [
            'title' => 'Plantenasiel Haarlem: planten brengen of ophalen | Gratis planten',
            'meta' => 'Bij Plantenasiel Haarlem kun je planten brengen of ophalen. Dinsdagavond open in wijkcentrum Alleman — een tweede kans voor planten in Haarlem.',
            'h1' => 'Plantenasiel Haarlem',
        ],
        'plantenasiel-leiden' => [
            'title' => 'Plantenasiel Leiden: planten brengen of ophalen | Gratis planten',
            'meta' => 'Bij Plantenasiel Leiden kun je planten brengen of ophalen. Broedplaats Hazewind is een vaste plek in Leiden voor planten die een nieuw thuis zoeken.',
            'h1' => 'Plantenasiel Leiden',
        ],
    ];

    foreach ($cases as $slug => $expected) {
        expect(mb_strlen($expected['meta']))->toBeGreaterThanOrEqual(140)
            ->and(mb_strlen($expected['meta']))->toBeLessThanOrEqual(155);

        $html = $this->get(route('organisaties.show', ['slug' => $slug]))
            ->assertOk()
            ->assertSee('<h1 class="page-title">'.$expected['h1'].'</h1>', false)
            ->getContent();

        $documentTitle = SeoTitle::document($expected['title']);
        expect($html)->toContain('<title>'.e($documentTitle).'</title>')
            ->and($html)->toContain('<meta name="description" content="'.e($expected['meta']).'"')
            ->and($html)->toContain('property="og:title" content="'.e($documentTitle).'"')
            ->and($html)->toContain('property="og:description" content="'.e($expected['meta']).'"');
    }
});

test('Leiden- en Utrecht-stadspagina linken naar plantenasiel-organisatie', function () {
    $cases = [
        'leiden' => [
            'org_slug' => 'plantenasiel-leiden',
            'anchor' => 'Plantenasiel Leiden: planten brengen of ophalen',
            'intro' => 'Ik ken in Leiden ook een plantenasiel waar je planten kunt brengen of ophalen.',
        ],
        'utrecht' => [
            'org_slug' => 'plantenhuis-utrecht',
            'anchor' => 'Het Plantenhuis (Plantenasiel Utrecht): planten brengen of ophalen',
            'intro' => 'Op zoek naar een plantenasiel in Utrecht? Bij Het Plantenhuis kun je kamerplanten brengen of ophalen.',
        ],
    ];

    foreach ($cases as $stad => $expected) {
        $orgUrl = route('organisaties.show', ['slug' => $expected['org_slug']]);

        $this->get(route('gratis-planten.show', ['stad' => $stad]))
            ->assertOk()
            ->assertSee('class="gp-related-org"', false)
            ->assertSee($expected['intro'], false)
            ->assertSee($expected['anchor'], false)
            ->assertSee('href="'.$orgUrl.'"', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }
});

test('SeoOverrides leest slug-keyed config zonder DB', function () {
    expect(SeoOverrides::organisatieTitle('plantenasiel-haarlem', 'fallback'))
        ->toBe('Plantenasiel Haarlem: planten brengen of ophalen | Gratis planten')
        ->and(SeoOverrides::organisatieTitle('onbekend-slug', 'fallback'))->toBe('fallback');

    $leiden = SeoOverrides::stadRelatedOrganisatie('leiden');
    expect($leiden)->not->toBeNull()
        ->and($leiden['organisatie_slug'])->toBe('plantenasiel-leiden')
        ->and(SeoOverrides::stadRelatedOrganisatie('amsterdam'))->toBeNull();
});
