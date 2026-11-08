<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\AanbodFoto;
use App\Models\User;
use App\Services\AanbodSearch;
use App\Support\AanbodTekst;
use App\Support\RecentAanbod;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function naamUser(string $email = 'naam-plicht@example.com'): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => 'Sam',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
        'active' => true,
        'email_verified_at' => now(),
    ]);
}

function naamAanbod(User $user, string $tekst, bool $metFoto = false): Aanbod
{
    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => $tekst,
        'aantal' => 2,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
    if ($metFoto) {
        AanbodFoto::query()->create([
            'aanbod_id' => $aanbod->id,
            'disk' => 'aanbod_fotos',
            'path' => 't/'.$aanbod->id.'.jpg',
            'thumb_path' => 't/'.$aanbod->id.'-t.jpg',
            'mime' => 'image/jpeg',
            'width' => 100,
            'height' => 100,
            'bytes' => 10,
            'sort_order' => 0,
            'alt' => '',
        ]);
    }

    return $aanbod;
}

beforeEach(function () {
    Storage::fake('aanbod_fotos');
});

test('gezocht met plantnaam zonder foto mag', function () {
    $user = naamUser('gezocht-naam@example.com');

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'ophalen',
            'tekst' => 'Tradescantia Blushing bride',
            'aantal' => 1,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHasNoErrors();

    expect(Aanbod::query()->where('user_id', $user->id)->value('type'))->toBe('ophalen')
        ->and(Aanbod::query()->where('user_id', $user->id)->value('tekst'))
        ->toBe('Tradescantia Blushing bride');
});

test('bewerken naar lege naam wordt afgewezen met behoud van bestaande tekst', function () {
    $user = naamUser('edit-leeg@example.com');
    $aanbod = naamAanbod($user, 'Monstera', true);

    $this->actingAs($user)
        ->post(route('account.aanbod.update', $aanbod), [
            'type' => 'uitdelen',
            'tekst' => '   ',
            'aantal' => 2,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst');

    expect($aanbod->fresh()->tekst)->toBe('Monstera');
});

test('publieke zoek-API sluit lege tekst uit en vult met volgende geldige', function () {
    $user = naamUser('api-leeg@example.com');
    $leeg = naamAanbod($user, '', true);
    $nbsp = naamAanbod($user, "\u{00A0}", true);
    $geldig = naamAanbod($user, 'Sierappel', true);

    $search = app(AanbodSearch::class);
    $recent = $search->recentWithFoto(6);
    $ids = array_column($recent, 'id');

    expect($ids)->toContain($geldig->id)
        ->and($ids)->not->toContain($leeg->id)
        ->and($ids)->not->toContain($nbsp->id);

    $payload = $this->getJson(route('api.zoeken', ['q' => 'Haarlem']))
        ->assertOk()
        ->json('items');
    $apiIds = array_column($payload, 'id');
    expect($apiIds)->toContain($geldig->id)
        ->and($apiIds)->not->toContain($leeg->id)
        ->and($apiIds)->not->toContain($nbsp->id);

    $teksten = array_column($payload, 'tekst');
    expect($teksten)->toContain('Sierappel');
});

test('home Net aangeboden toont geen lege titel en wel volgende naam', function () {
    config(['gratisplanten.recent_aanbod_min' => 1]);
    $user = naamUser('home-leeg@example.com');
    naamAanbod($user, '', true);
    naamAanbod($user, 'Pompoen', true);

    expect(RecentAanbod::shouldShow(app(AanbodSearch::class)->countPublicWithFoto()))->toBeTrue();

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('Net aangeboden')
        ->and($html)->toContain('Pompoen')
        ->and($html)->toContain('aanbod-kaart-naam')
        ->and($html)->toContain('data-aanbod-foto')
        ->and($html)->toContain('onerror=');
});

test('aanbod zonder foto blijft zichtbaar in publieke zoeklijst', function () {
    $user = naamUser('zonder-foto@example.com');
    $aanbod = naamAanbod($user, 'Cosmea', false);

    $items = app(AanbodSearch::class)->search('Haarlem', null, false)['items'];
    $ids = array_column($items, 'id');
    expect($ids)->toContain($aanbod->id);
});

test('fouttekst plantnaam is exact de UX-copy', function () {
    expect(AanbodTekst::FOUT_VERPLICHT)->toBe('Vul de naam van je plant in.');

    $user = naamUser('fout-copy@example.com');
    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => '',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors(['tekst' => AanbodTekst::FOUT_VERPLICHT]);
});

test('gp:audit-lege-aanbod-tekst is read-only en toont ids zonder PII', function () {
    $user = naamUser('audit@example.com');
    $leeg = naamAanbod($user, '', true);
    naamAanbod($user, 'Oké plant', false);

    $exit = Artisan::call('gp:audit-lege-aanbod-tekst', ['--json' => true, '--only-public' => true]);
    expect($exit)->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain('"dry_run": true')
        ->and($out)->toContain('"id": '.$leeg->id)
        ->and($out)->toContain('"reden": "leeg"')
        ->and($out)->not->toContain($user->email)
        ->and($out)->not->toContain('@example.com');

    expect(Aanbod::query()->find($leeg->id)?->tekst)->toBe('');
});
