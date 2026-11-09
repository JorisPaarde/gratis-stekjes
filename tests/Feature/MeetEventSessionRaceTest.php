<?php

declare(strict_types=1);

use App\Models\ConversionEvent;
use App\Models\GeocodeCache;
use App\Support\FormGuard;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/** @param  array<string, mixed>  $extra */
function meetRacePayload(array $extra = []): array
{
    return array_merge([
        FormGuard::HONEYPOT_FIELD => '',
        'website' => '',
        'form_started' => time(),
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'email' => 'meet-race@example.com',
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
});

test('late sessie-write wist register-flash (model van de beacon-race)', function () {
    $this->get(route('account.registreren'))->assertOk();

    $session = session();
    $id = $session->getId();
    $session->save();
    $stalePayload = $session->getHandler()->read($id);
    expect($stalePayload)->not->toBe('');

    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), meetRacePayload())
        ->assertRedirect(route('account.registreren', [
            'fout' => 'snel',
            'email' => 'meet-race@example.com',
        ]))
        ->assertSessionHasErrors('form_started');

    // Simuleer sendBeacon die de sessie vóór de flash las en ná de register-POST terugschreef.
    $session->getHandler()->write($id, $stalePayload);

    // Laravel hergebruikt de gestarte Store in één testproces; drop drivers zodat
    // de volgende request opnieuw uit de handler leest (zoals een echte 2e PHP-worker).
    $manager = app('session');
    (function (): void {
        $this->drivers = [];
    })->call($manager);
    $this->app->forgetInstance('session.store');

    $html = $this->get(route('account.registreren'))->assertOk()->getContent();
    expect($html)->not->toContain('Dat ging erg snel')
        ->and($html)->not->toContain('meet-race@example.com');
});

test('meet/event na te-snelle register wist flash niet en telt form_start', function () {
    $this->get(route('account.registreren'))->assertOk();

    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), meetRacePayload([
            'email' => 'meet-keep@example.com',
        ]))
        ->assertRedirect(route('account.registreren', [
            'fout' => 'snel',
            'email' => 'meet-keep@example.com',
        ]))
        ->assertSessionHasErrors('form_started');

    $beacon = $this->post(route('meet.event'), [
        'type' => 'form_start',
        'path' => '/account/registreren',
    ]);
    $beacon->assertNoContent();

    $sessionCookie = (string) config('session.cookie');
    $setCookieNames = collect($beacon->headers->getCookies())->map->getName()->all();
    expect($setCookieNames)->not->toContain($sessionCookie);

    expect(ConversionEvent::query()
        ->where('event_type', ConversionEvent::TYPE_FORM_START)
        ->where('path', '/account/registreren')
        ->count())->toBe(1);

    $html = $this->get(route('account.registreren'))->assertOk()->getContent();
    expect($html)->toContain('Dat ging erg snel')
        ->and($html)->toContain('meet-keep@example.com')
        ->and($html)->toContain('error-list');
});

test('meet/event route gebruikt beacon-middleware zonder StartSession', function () {
    $route = app('router')->getRoutes()->getByName('meet.event');
    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('beacon')
        ->and($middleware)->not->toContain('web');
});
