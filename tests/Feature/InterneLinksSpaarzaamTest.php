<?php

declare(strict_types=1);

/**
 * Spaarzame interne links (0.81.2): per pagina aanwezigheid + doel-URL geeft 200.
 *
 * @return list<array{pagina: string, anker: string, doel: string}>
 */
function spaarzameInterneLinks(): array
{
    return [
        [
            'pagina' => route('aanbod'),
            'anker' => 'vaste plekken',
            'doel' => route('deelplekken'),
        ],
        [
            'pagina' => route('aanbod'),
            'anker' => 'gratis planten per stad',
            'doel' => route('gratis-planten.index'),
        ],
        [
            'pagina' => route('hoe-werkt-het'),
            'anker' => 'gratis planten in jouw stad',
            'doel' => route('gratis-planten.index'),
        ],
        [
            'pagina' => route('hoe-werkt-het'),
            'anker' => 'planten vermeerderen',
            'doel' => route('vermeerderen'),
        ],
        [
            'pagina' => route('stektips.index'),
            'anker' => 'gratis in de buurt',
            'doel' => route('aanbod'),
        ],
        [
            'pagina' => route('vermeerderen'),
            'anker' => 'Kijk wat er gratis in de buurt wordt aangeboden',
            'doel' => route('aanbod'),
        ],
        [
            'pagina' => route('vermeerderen'),
            'anker' => 'zadenbibliotheek in de buurt',
            'doel' => route('deelplekken', ['type' => 'zaadbibliotheek']),
        ],
        [
            'pagina' => route('deelplekken'),
            'anker' => 'gratis planten per stad',
            'doel' => route('gratis-planten.index'),
        ],
        [
            'pagina' => route('contact'),
            'anker' => 'hoe het werkt',
            'doel' => route('hoe-werkt-het'),
        ],
        [
            'pagina' => route('contact'),
            'anker' => 'een plek of organisatie aanmelden',
            'doel' => route('voor-organisaties'),
        ],
        [
            'pagina' => route('help-mee'),
            'anker' => 'wat het voor een tuinvereniging betekent',
            'doel' => route('tuinverenigingen'),
        ],
        [
            'pagina' => route('tuinverenigingen'),
            'anker' => 'poster voor het clubhuis',
            'doel' => route('poster', ['voor' => 'volkstuin']),
        ],
        [
            'pagina' => route('struikroven'),
            'anker' => 'gratis planten per stad',
            'doel' => route('gratis-planten.index'),
        ],
        [
            'pagina' => route('struikroven'),
            'anker' => 'agenda met ruildagen en uitdeeldagen',
            'doel' => route('agenda'),
        ],
        [
            'pagina' => route('gratis-planten.index'),
            'anker' => 'vaste deelplekken',
            'doel' => route('deelplekken'),
        ],
        [
            'pagina' => route('invasieve-soorten'),
            'anker' => 'inheemse planten',
            'doel' => route('stektips.index', ['tag' => 'inheems']),
        ],
    ];
}

test('spaarzame interne links staan op de pagina en doel geeft 200', function () {
    foreach (spaarzameInterneLinks() as $link) {
        $html = $this->get($link['pagina'])->assertOk()->getContent();

        $href = e($link['doel']);
        $anker = e($link['anker']);
        expect($html)->toContain('href="'.$href.'"')
            ->and($html)->toContain('>'.$anker.'</a>');

        $this->get($link['doel'])->assertOk();
    }
});

test('stekchallenge deelt initiatief-view maar krijgt geen struikroven-only links', function () {
    $html = $this->get(route('stekchallenge'))->assertOk()->getContent();

    expect($html)->not->toContain('>gratis planten per stad</a>')
        ->and($html)->not->toContain('>agenda met ruildagen en uitdeeldagen</a>');
});
