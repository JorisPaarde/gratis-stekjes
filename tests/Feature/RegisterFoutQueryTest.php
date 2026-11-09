<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Support\FormGuard;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/** @param  array<string, mixed>  $extra */
function foutQueryPayload(array $extra = []): array
{
    return array_merge([
        FormGuard::HONEYPOT_FIELD => '',
        'website' => '',
        'form_started' => time(),
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'email' => 'fout-query@example.com',
        'password' => implode('-', ['TestOnly', 'Auth', 'Passphrase', '9']),
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
    Notification::fake();
    Log::spy();
    RateLimiter::clear('register:127.0.0.1');
});

test('te snelle register-redirect bevat alleen fout=snel (geen e-mail in query)', function () {
    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), foutQueryPayload())
        ->assertRedirect(route('account.registreren', ['fout' => 'snel']))
        ->assertSessionHasErrors('form_started');

    expect(session()->getOldInput('email'))->toBe('fout-query@example.com');
});

test('GET met fout=snel toont melding zonder sessie-flash', function () {
    $html = $this->get(route('account.registreren', ['fout' => 'snel']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Dat ging erg snel')
        ->and($html)->toContain('error-list')
        ->and($html)->not->toContain('fout-query@example.com');
});

test('GET met fout=teveel toont neutrale throttle-vangnettekst', function () {
    $html = $this->get(route('account.registreren', ['fout' => 'teveel']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Even te veel pogingen. Probeer het straks opnieuw.')
        ->and($html)->toContain('error-list')
        ->and($html)->not->toContain('over een minuut')
        ->and($html)->not->toContain('over een uur');
});

test('na te snelle submit blijft melding zichtbaar als flash gewist is', function () {
    $response = $this->from(route('account.registreren'))
        ->post(route('account.registreren'), foutQueryPayload([
            'email' => 'flash-kwijt@example.com',
        ]));

    $response->assertRedirect(route('account.registreren', ['fout' => 'snel']));
    $location = $response->headers->get('Location');
    expect($location)->toContain('fout=snel')
        ->and($location)->not->toContain('flash-kwijt')
        ->and($location)->not->toContain('email=');

    // Wis flash (simuleer file-session race) maar volg de Location mét fout-query.
    session()->flush();
    $this->app->forgetInstance('session.store');
    $manager = app('session');
    (function (): void {
        $this->drivers = [];
    })->call($manager);

    $html = $this->get($location)->assertOk()->getContent();
    expect($html)->toContain('Dat ging erg snel')
        ->and($html)->not->toContain('flash-kwijt@example.com');
});
