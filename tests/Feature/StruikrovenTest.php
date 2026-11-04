<?php

declare(strict_types=1);

test('Struikroven-pagina legt uit hoe je meedoet en verwijst naar aanbod en aanbieden', function () {
    $html = $this->get(route('organisaties.show', ['slug' => 'struikroven']))->assertOk()->getContent();
    expect($html)->toContain('Stichting Struikroven')
        ->and($html)->toContain('Struikroven en Gratis planten vullen elkaar aan')
        ->and($html)->toContain('class="kb-kaarten"')
        ->and($html)->toContain('class="kb-stappen"')
        ->and($html)->toContain('https://www.struikroven.nu/agenda')
        ->and($html)->toContain('href="'.route('aanbod').'"')
        ->and($html)->toContain('neemt geen planten van particulieren aan');
});

test('De Roverstuin Utrecht staat niet meer op de kaart', function () {
    $json = $this->getJson(route('api.deelplekken'))->assertOk()->getContent();
    expect($json)->not->toContain('Roverstuin');
});
