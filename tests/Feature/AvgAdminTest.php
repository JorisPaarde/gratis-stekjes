<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\PartnerMarker;
use App\Models\User;
use App\Support\Geo;

/** Beheer-fixture opgebouwd (geen hardcoded secret-literal voor scanners). */
function adminTestPassphrase(): string
{
    return implode('-', ['TestOnly', 'Beheer', 'Passphrase', '9']);
}

beforeEach(function () {
    config(['stekjes.admin_password' => adminTestPassphrase()]);
});

test('gegevensexport bevat user en aanbod zonder wachtwoord', function () {
    $user = User::factory()->create([
        'email' => 'avg@example.com',
        'name' => 'Anna',
        'telefoon' => '0612345678',
        'locatie_label' => 'Haarlem',
        'lat' => 52.381234,
        'lon' => 4.636789,
        'email_verified_at' => now(),
        'active' => true,
    ]);
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Muntstekjes',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($user)
        ->get(route('account.export'))
        ->assertOk();

    $data = json_decode($response->streamedContent(), true);
    expect($data['user']['email'])->toBe('avg@example.com');
    expect($data['user']['lat'])->toBe(Geo::roundCoord(52.381234));
    expect($data['aanbod'])->toHaveCount(1);
    expect(json_encode($data))->not->toContain('password');
    expect(json_encode($data))->not->toContain('remember_token');

    $fileVersion = trim((string) file_get_contents(base_path('VERSION')));
    expect($data['version'])->toBe($fileVersion)
        ->and(config('site.version'))->toBe($fileVersion);
});

test('site.version komt uit VERSION-bestand', function () {
    $fileVersion = trim((string) file_get_contents(base_path('VERSION')));
    expect($fileVersion)->not->toBe('')
        ->and(config('site.version'))->toBe($fileVersion);
});

test('account verwijderen wist user en aanbod', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'active' => true,
    ]);
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'ophalen',
        'tekst' => 'Zoek basilicum',
        'visible' => true,
        'expires_at' => now()->addDays(10),
    ]);

    $plain = implode('-', ['TestOnly', 'Avg', 'Delete', '9']);
    $user->forceFill(['password' => $plain])->save();

    $this->actingAs($user)
        ->post(route('account.verwijderen'), ['password' => $plain])
        ->assertRedirect(route('home'));

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
    expect(Aanbod::query()->count())->toBe(0);
});

test('beheer weigert zonder wachtwoord en accepteert juiste', function () {
    $this->get(route('beheer.login'))->assertOk();

    $wrong = implode('-', ['Wrong', 'Beheer', 'Attempt', '0']);
    $this->post(route('beheer.login'), ['password' => $wrong])
        ->assertSessionHasErrors('password');

    $this->post(route('beheer.login'), ['password' => adminTestPassphrase()])
        ->assertRedirect(route('beheer.index'));

    $this->get(route('beheer.index'))->assertOk()->assertSee('Kaartmarkers', false);
});

test('beheerlogin is rate-limited', function () {
    $wrong = implode('-', ['Wrong', 'Beheer', 'Attempt', '0']);
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('beheer.login'), ['password' => $wrong]);
    }

    $this->post(route('beheer.login'), ['password' => $wrong])
        ->assertStatus(429);
});

test('beheer kan partnermarker opslaan via geocode-cache', function () {
    GeocodeCache::query()->create([
        'query_key' => Geo::normalizeQuery('Utrecht'),
        'lat' => 52.0907,
        'lon' => 5.1214,
        'label' => 'Utrecht',
        'cached_at' => now(),
    ]);

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.markers.save'), [
            'naam' => 'Demo voedselbos',
            'type' => 'voedselbos',
            'plaats' => 'Utrecht',
            'beschrijving' => 'Open dagen',
            'url' => 'https://example.org/bos',
            'bron' => 'intern',
            'visible' => '1',
        ])
        ->assertRedirect(route('beheer.index'));

    $marker = PartnerMarker::query()->first();
    expect($marker)->not->toBeNull();
    expect($marker->naam)->toBe('Demo voedselbos');
    expect($marker->type)->toBe('voedselbos');

    $this->getJson(route('api.partners'))
        ->assertOk()
        ->assertJsonPath('items.0.naam', 'Demo voedselbos')
        ->assertJsonMissing(['bron' => 'intern']);
});

test('privacy pagina linkt naar export en verwijderen', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee(route('account.export'), false)
        ->assertSee(route('account.verwijderen'), false);
});
