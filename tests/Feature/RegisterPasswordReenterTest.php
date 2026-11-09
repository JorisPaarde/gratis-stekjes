<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Support\FormGuard;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

function registerReenterPassword(): string
{
    return implode('-', ['TestOnly', 'Reenter', 'Passphrase', '9']);
}

/** @param  array<string, mixed>  $extra */
function registerReenterPayload(array $extra = []): array
{
    return array_merge([
        FormGuard::HONEYPOT_FIELD => '',
        'website' => '',
        'form_started' => time() - 10,
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'email' => 'reenter@example.com',
        'password' => registerReenterPassword(),
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

test('na privacy-fout blijft wachtwoord uit HTML en toont opnieuw-in melding', function () {
    $response = $this->from(route('account.registreren'))
        ->post(route('account.registreren'), registerReenterPayload([
            'privacy' => null,
        ]));

    $response->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors('privacy');
    expect(session()->getOldInput('password'))->toBeNull()
        ->and(session()->getOldInput('email'))->toBe('reenter@example.com');

    $html = $this->followRedirects($response)->assertOk()->getContent();

    expect($html)->toContain('Vul je wachtwoord opnieuw in')
        ->and($html)->toContain('id="reg-password-hint"')
        ->and($html)->toContain('data-has-errors="1"')
        ->and($html)->toContain('scrollIntoView')
        ->and($html)->toContain('aria-invalid="true"')
        ->and($html)->not->toContain(registerReenterPassword())
        ->and($html)->not->toMatch('/name="password"[^>]*value="/');
});

test('wachtwoord.verplicht na eerdere fout gebruikt opnieuw-in tekst', function () {
    // Simuleer tweede submit: e-mail nog bekend, wachtwoord leeg (niet teruggezet).
    $response = $this->from(route('account.registreren'))
        ->post(route('account.registreren'), registerReenterPayload([
            'password' => '',
            'privacy' => '1',
        ]));

    $response->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors([
            'password' => 'Vul je wachtwoord opnieuw in.',
        ]);

    $html = $this->followRedirects($response)->assertOk()->getContent();
    expect($html)->toContain('Vul je wachtwoord opnieuw in')
        ->and($html)->not->toContain(registerReenterPassword());
});

test('te-snel blijft werken met melding en zonder wachtwoord in HTML', function () {
    $response = $this->from(route('account.registreren'))
        ->post(route('account.registreren'), registerReenterPayload([
            'form_started' => time(),
        ]));

    $response->assertRedirect(route('account.registreren', ['fout' => 'snel']))
        ->assertSessionHasErrors([
            'form_started' => 'Dat ging erg snel. Klik nog een keer op Account maken.',
        ]);

    expect(session()->getOldInput('password'))->toBeNull();

    $html = $this->followRedirects($response)->assertOk()->getContent();
    expect($html)->toContain('Dat ging erg snel')
        ->and($html)->toContain('Vul je wachtwoord opnieuw in')
        ->and($html)->not->toContain(registerReenterPassword());
});
