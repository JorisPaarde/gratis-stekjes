<?php

declare(strict_types=1);

use App\Services\Kennisbank;

test('kennisbank filtert op categorie seizoen en zoekterm', function () {
    $kb = new Kennisbank(dirname(__DIR__, 2).'/data/kennisbank');

    $wortel = $kb->filter(['categorie' => 'wortelbevordering']);
    $slugs = collect($wortel)->pluck('slug');
    expect($slugs)->toContain('wilgenwater')
        ->and($slugs)->toContain('stekpoeder');

    $winter = $kb->filter(['seizoen' => 'winter']);
    expect(collect($winter)->pluck('slug'))->toContain('winterhoutstek');

    $zoek = $kb->filter(['q' => 'gilde']);
    expect(collect($zoek)->pluck('slug'))->toContain('permacultuur-gilde-voedselbos');

    $related = $kb->relatedArticles($kb->find('stekpoeder'));
    expect(collect($related)->pluck('slug'))->toContain('wilgenwater');

    $voorWilg = $kb->articlesForSoort('wilg');
    expect(collect($voorWilg)->pluck('slug'))->toContain('wilgenwater');
});

test('elk artikel heeft centrale zoekwoorden en geldige categorie', function () {
    $kb = new Kennisbank(dirname(__DIR__, 2).'/data/kennisbank');
    $valid = array_column($kb->categories(), 'slug');

    foreach ($kb->all() as $artikel) {
        expect($artikel['zoekwoorden'])->toBeArray()->not->toBeEmpty();
        foreach ($artikel['categorieen'] as $cat) {
            expect($valid)->toContain($cat);
        }
        foreach ($artikel['bronnen'] as $bron) {
            expect($bron['url'])->toMatch('#^https?://#');
        }
    }
});
