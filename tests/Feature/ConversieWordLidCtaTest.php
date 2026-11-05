<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\WordLidCta;

test('Word lid CTA staat op home, aanbod en hoe-werkt-het voor gasten', function () {
    foreach (['/', '/aanbod', '/hoe-werkt-het'] as $pad) {
        $html = $this->get($pad)->assertOk()->getContent();
        expect($html)->toContain('Word lid')
            ->and($html)->toContain('/ga/word-lid');
        expect(substr_count($html, 'Word lid'))->toBeGreaterThanOrEqual(2);
    }
});

test('ingelogd lid ziet Bied iets aan i.p.v. Word lid op die pagina\'s', function () {
    $user = User::factory()->create(['name' => 'Sam']);
    foreach (['/', '/aanbod', '/hoe-werkt-het'] as $pad) {
        $html = $this->actingAs($user)->get($pad)->assertOk()->getContent();
        expect($html)->toContain('Bied iets aan')
            ->and($html)->toContain(route('aanbieden', absolute: false));
    }
});

test('WordLidCta helper wijst gasten naar /ga/word-lid met utm_content', function () {
    expect(WordLidCta::guestUrl('home-hero', '/'))
        ->toContain('/ga/word-lid')
        ->toContain('utm_content=home-hero')
        ->toContain('from=');
    expect(WordLidCta::label(null))->toBe('Word lid');
    expect(WordLidCta::label(User::factory()->make()))->toBe('Bied iets aan');
});
