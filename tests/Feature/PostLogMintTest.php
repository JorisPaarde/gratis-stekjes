<?php

declare(strict_types=1);

use App\Models\PostLog;

beforeEach(function () {
    config(['stekjes.post_log_mint_token' => 'test-mint-token']);
});

test('mint maakt post_log met tracking_url via /ga/word-lid', function () {
    $response = $this->postJson('/api/internal/post-logs', [
        'titel' => 'Oproep stekjes delen',
        'groep' => 'Plantenruil Amsterdam',
        'soort' => 'aankondiging',
        'utm_source' => 'instagram',
        'link' => 'https://www.instagram.com/p/example/',
        'notitie' => 'plantjes',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('titel', 'Oproep stekjes delen')
        ->assertJsonPath('groep', 'Plantenruil Amsterdam')
        ->assertJsonPath('soort', 'aankondiging')
        ->assertJsonPath('utm.source', 'instagram')
        ->assertJsonPath('utm.medium', 'social')
        ->assertJsonPath('utm.campaign', 'plantenruil-amsterdam');

    $id = (int) $response->json('id');
    expect($id)->toBeGreaterThan(0)
        ->and($response->json('utm.content'))->toBe((string) $id);

    $url = (string) $response->json('tracking_url');
    expect($url)->toContain('/ga/word-lid?')
        ->and($url)->toContain('utm_content='.$id)
        ->and($url)->toContain('utm_source=instagram')
        ->and($url)->toContain('utm_medium=social')
        ->and($url)->toContain('utm_campaign=plantenruil-amsterdam');

    $shortUrl = (string) $response->json('short_url');
    $shortCode = (string) $response->json('short_code');
    expect($shortCode)->toMatch('/^[2-9A-HJ-NP-Z]{5}$/')
        ->and($shortUrl)->toEndWith('/s/'.$shortCode);

    $post = PostLog::query()->find($id);
    expect($post)->not->toBeNull()
        ->and($post->titel)->toBe('Oproep stekjes delen')
        ->and($post->groep_slug)->toBe('plantenruil-amsterdam')
        ->and($post->utm_source)->toBe('instagram')
        ->and($post->short_code)->toBe($shortCode)
        ->and($post->trackingUrl())->toBe($url)
        ->and($post->shortUrl())->toBe($shortUrl);
});

test('mint zonder of met verkeerde token geeft 403', function () {
    $payload = [
        'titel' => 'X',
        'groep' => 'Y',
        'soort' => 'aankondiging',
    ];

    $this->postJson('/api/internal/post-logs', $payload)->assertForbidden();
    $this->postJson('/api/internal/post-logs', $payload, [
        'X-Post-Log-Token' => 'wrong',
    ])->assertForbidden();

    expect(PostLog::query()->count())->toBe(0);
});

test('mint met lege token-config geeft 403', function () {
    config(['stekjes.post_log_mint_token' => '']);

    $this->postJson('/api/internal/post-logs', [
        'titel' => 'X',
        'groep' => 'Y',
        'soort' => 'aankondiging',
    ], [
        'X-Post-Log-Token' => 'anything',
    ])->assertForbidden();

    expect(PostLog::query()->count())->toBe(0);
});

test('mint validatiefouten geven 422', function () {
    $this->postJson('/api/internal/post-logs', [
        'groep' => 'Alleen groep',
        'soort' => 'niet-bestaand',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['titel', 'soort']);

    expect(PostLog::query()->count())->toBe(0);
});

test('mint zonder Accept-json geeft bij validatiefout alsnog 422 JSON', function () {
    $response = $this->post('/api/internal/post-logs', [
        'groep' => 'Alleen groep',
        'soort' => 'niet-bestaand',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['titel', 'soort']])
        ->assertHeader('Content-Type', 'application/json');

    expect(PostLog::query()->count())->toBe(0);
});

test('mint weigert whitespace-only titel en groep met 422', function () {
    $this->postJson('/api/internal/post-logs', [
        'titel' => "  \t  ",
        'groep' => '   ',
        'soort' => 'aankondiging',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['titel', 'groep']);

    expect(PostLog::query()->count())->toBe(0);
});

test('mint default utm_source facebook en posted_on vandaag', function () {
    $response = $this->postJson('/api/internal/post-logs', [
        'titel' => 'Korte post',
        'groep' => 'Stekjes Utrecht',
        'soort' => 'oproep',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('utm.source', 'facebook')
        ->assertJsonPath('posted_on', now()->toDateString());
});

test('PostLog trackingUrl gebruikt /ga/word-lid pad', function () {
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Unit check',
        'groep' => 'Testgroep',
        'groep_slug' => 'testgroep',
        'soort' => 'uitleg',
        'utm_source' => 'facebook',
    ]);

    $url = $post->trackingUrl('https://example.test');
    expect($url)->toStartWith('https://example.test/ga/word-lid?')
        ->and($url)->toContain('utm_content='.$post->id)
        ->and($url)->not->toContain('https://example.test/?');
});

test('mint met target_path landt op dat pad met UTM', function () {
    $response = $this->postJson('/api/internal/post-logs', [
        'titel' => 'Tuinverenigingen post',
        'groep' => 'Plantenruil Amsterdam',
        'soort' => 'aankondiging',
        'utm_source' => 'facebook',
        'target_path' => '/tuinverenigingen',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('target_path', '/tuinverenigingen');

    $id = (int) $response->json('id');
    $url = (string) $response->json('tracking_url');
    expect($url)->toContain('/tuinverenigingen?')
        ->and($url)->not->toContain('/ga/word-lid')
        ->and($url)->toContain('utm_source=facebook')
        ->and($url)->toContain('utm_medium=social')
        ->and($url)->toContain('utm_campaign=plantenruil-amsterdam')
        ->and($url)->toContain('utm_content='.$id);

    $post = PostLog::query()->find($id);
    expect($post)->not->toBeNull()
        ->and($post->target_path)->toBe('/tuinverenigingen')
        ->and($post->resolvedTargetPath())->toBe('/tuinverenigingen')
        ->and($post->trackingPath())->toStartWith('/tuinverenigingen?');
});

test('mint zonder target_path blijft /ga/word-lid', function () {
    $response = $this->postJson('/api/internal/post-logs', [
        'titel' => 'Default pad',
        'groep' => 'Stekjes Utrecht',
        'soort' => 'oproep',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('target_path', '/ga/word-lid');

    $post = PostLog::query()->find((int) $response->json('id'));
    expect($post->target_path)->toBeNull()
        ->and($post->resolvedTargetPath())->toBe('/ga/word-lid')
        ->and((string) $response->json('tracking_url'))->toContain('/ga/word-lid?');
});

test('mint weigert open-redirect target_path met 422', function (string $bad) {
    $this->postJson('/api/internal/post-logs', [
        'titel' => 'Bad path',
        'groep' => 'Test',
        'soort' => 'anders',
        'target_path' => $bad,
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['target_path']);

    expect(PostLog::query()->count())->toBe(0);
})->with([
    'https absolute' => 'https://evil.example/phish',
    'protocol-relative' => '//evil.com',
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,hi',
    'backslash' => '/\\evil',
    'query in path' => '/tuinverenigingen?x=1',
    'colon in path' => '/javascript:alert(1)',
    'no leading slash' => 'tuinverenigingen',
]);

test('mint accepteert genest intern pad zoals /stektips/kiwibes', function () {
    $response = $this->postJson('/api/internal/post-logs', [
        'titel' => 'Kiwibes tip',
        'groep' => 'Stektips',
        'soort' => 'uitleg',
        'utm_source' => 'instagram',
        'target_path' => '/stektips/kiwibes',
    ], [
        'X-Post-Log-Token' => 'test-mint-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('target_path', '/stektips/kiwibes');

    $id = (int) $response->json('id');
    expect((string) $response->json('tracking_url'))
        ->toContain('/stektips/kiwibes?')
        ->and((string) $response->json('tracking_url'))->toContain('utm_content='.$id)
        ->and((string) $response->json('tracking_url'))->toContain('utm_source=instagram');
});
