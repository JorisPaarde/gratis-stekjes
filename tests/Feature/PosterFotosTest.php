<?php

declare(strict_types=1);

use App\Http\Controllers\MeedoenController;

test('elke poster heeft een foto met maker, bron en licentie', function () {
    foreach (MeedoenController::POSTERS as $voor => $poster) {
        expect($poster)->toHaveKey('foto');
        foreach (['alt', 'maker', 'bron_url', 'licentie', 'positie'] as $sleutel) {
            expect($poster['foto'][$sleutel] ?? '')->not->toBe('', "$voor mist foto.$sleutel");
        }
        expect($poster['foto']['bron_url'])->toStartWith('https://unsplash.com/photos/');
        foreach (['480', '1600'] as $breedte) {
            foreach (['jpg', 'webp'] as $ext) {
                expect(public_path("assets/images/posters/{$voor}-{$breedte}.{$ext}"))->toBeFile();
            }
        }
    }
});

test('posterpagina toont de foto en de fotocredit', function () {
    foreach (MeedoenController::POSTERS as $voor => $poster) {
        $html = $this->get(route('poster', ['voor' => $voor]))->assertOk()->getContent();
        expect($html)->toContain("/assets/images/posters/{$voor}-1600.webp")
            ->and($html)->toContain("/assets/images/posters/{$voor}-1600.jpg")
            ->and($html)->toContain('poster--foto')
            ->and($html)->toContain($poster['foto']['bron_url'])
            ->and($html)->toContain(e($poster['foto']['maker']).' / Unsplash');
    }
});

test('help-mee toont posterkaarten met voorbeeld en actie', function () {
    $html = $this->get(route('help-mee'))->assertOk()->getContent();
    expect(substr_count($html, 'class="poster-kaart"'))->toBe(count(MeedoenController::POSTERS))
        ->and($html)->toContain('poster-mini')
        ->and($html)->toContain('Bekijk en print poster');
    foreach (array_keys(MeedoenController::POSTERS) as $voor) {
        expect($html)->toContain(route('poster', ['voor' => $voor]))
            ->and($html)->toContain("/assets/images/posters/{$voor}-480.webp");
    }
});
