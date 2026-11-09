<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Models\User;
use App\Support\FormGuard;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

function registerFlashPassword(): string
{
    return implode('-', ['TestOnly', 'Auth', 'Passphrase', '9']);
}

/** @param  array<string, mixed>  $extra */
function registerFlashPayload(array $extra = []): array
{
    return array_merge([
        FormGuard::HONEYPOT_FIELD => '',
        'website' => '',
        'form_started' => time() - 10,
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'email' => 'flash@example.com',
        'password' => registerFlashPassword(),
    ], $extra);
}

beforeEach(function () {
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011 AB')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    Http::fake();
    RateLimiter::clear('register:127.0.0.1');
    Notification::fake();
});

test('twee te snelle submits behouden melding en e-mail/land/postcode', function () {
    Log::spy();

    $first = $this->from(route('account.registreren'))
        ->post(route('account.registreren'), registerFlashPayload([
            'email' => 'dubbel-snel@example.com',
            'form_started' => time(),
        ]));

    $first->assertRedirect(route('account.registreren', ['fout' => 'snel']))
        ->assertSessionHasErrors([
            'form_started' => 'Dat ging erg snel. Klik nog een keer op Account maken.',
        ]);
    expect(session()->getOldInput('email'))->toBe('dubbel-snel@example.com')
        ->and(session()->getOldInput('country'))->toBe('NL')
        ->and(session()->getOldInput('postcode'))->toBe('2011 AB')
        ->and(session()->getOldInput('password'))->toBeNull();

    $afterFirst = $this->followRedirects($first);
    $afterFirst->assertSee('Dat ging erg snel', false)
        ->assertSee('dubbel-snel@example.com', false);

    $second = $this->from(route('account.registreren'))
        ->post(route('account.registreren'), registerFlashPayload([
            'email' => 'dubbel-snel@example.com',
            'form_started' => time(),
        ]));

    $second->assertRedirect(route('account.registreren', ['fout' => 'snel']))
        ->assertSessionHasErrors('form_started')
        ->assertSessionMissing('status');
    expect(session()->getOldInput('email'))->toBe('dubbel-snel@example.com')
        ->and(session()->getOldInput('password'))->toBeNull()
        ->and(User::query()->where('email', 'dubbel-snel@example.com')->exists())->toBeFalse()
        ->and(RateLimiter::attempts('register:127.0.0.1'))->toBe(0);

    $this->followRedirects($second)
        ->assertSee('Dat ging erg snel', false)
        ->assertSee('dubbel-snel@example.com', false);
});

test('register throttle zonder Referer toont melding op formulier met behouden input', function () {
    for ($i = 0; $i < 8; $i++) {
        RateLimiter::hit('register:127.0.0.1', 3600);
    }

    // Geen from()/Referer: voorheen back() → / en flash verdween vóór het formulier.
    $response = $this->post(route('account.registreren'), registerFlashPayload([
        'email' => 'te-vaak@example.com',
    ]));

    $response->assertRedirect(route('account.registreren', ['fout' => 'teveel']))
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('Even te veel pogingen. Probeer het over een uur opnieuw.')
        ->and(session()->getOldInput('email'))->toBe('te-vaak@example.com')
        ->and(session()->getOldInput('country'))->toBe('NL')
        ->and(session()->getOldInput('postcode'))->toBe('2011 AB')
        ->and(session()->getOldInput('password'))->toBeNull()
        ->and(session()->getOldInput('form_started'))->toBeNull()
        ->and(session()->getOldInput(FormGuard::HONEYPOT_FIELD))->toBeNull();

    $html = $this->followRedirects($response)->assertOk()->getContent();
    expect($html)->toContain('error-list')
        ->and($html)->toContain('Even te veel pogingen. Probeer het over een uur opnieuw.')
        ->and($html)->toContain('te-vaak@example.com')
        ->and($html)->not->toContain(registerFlashPassword());
});
