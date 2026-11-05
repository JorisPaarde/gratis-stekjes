<?php

declare(strict_types=1);

use App\Services\InitiatiefPaginas;
use App\Services\Organisaties;
use Illuminate\Support\Facades\Route;

test('onbekende initiatief-slug geeft 404', function () {
    $this->get('/bestaat-dit-initiatief-niet')
        ->assertNotFound();
});

test('initiatieven in organisaties.json zijn geldig (slug, reserved, uniek)', function () {
    expect(InitiatiefPaginas::reservedSlugs())->toContain('aanbod')
        ->and(InitiatiefPaginas::reservedSlugs())->toContain('initiatieven');
    expect(app(InitiatiefPaginas::class)->validate())->toBe([]);
});

test('validate vangt reserved en dubbele slugs', function () {
    $pad = tempnam(sys_get_temp_dir(), 'org');
    file_put_contents($pad, json_encode(['meta' => [], 'organisaties' => [
        ['slug' => 'a', 'naam' => 'A', 'type' => 'x', 'plaats' => 'p', 'website' => 'https://a.nl', 'bron_url' => 'https://a.nl', 'kort' => 'k', 'initiatief' => ['slug' => 'aanbod']],
        ['slug' => 'b', 'naam' => 'B', 'type' => 'x', 'plaats' => 'p', 'website' => 'https://b.nl', 'bron_url' => 'https://b.nl', 'kort' => 'k', 'initiatief' => ['slug' => 'zelfde', 'aanmeld_param' => 'x']],
        ['slug' => 'c', 'naam' => 'C', 'type' => 'x', 'plaats' => 'p', 'website' => 'https://c.nl', 'bron_url' => 'https://c.nl', 'kort' => 'k', 'initiatief' => ['slug' => 'zelfde', 'aanmeld_param' => 'x']],
    ]]));
    $errors = (new InitiatiefPaginas(new Organisaties($pad)))->validate();
    unlink($pad);

    expect($errors)->toHaveCount(3);
});

test('organisatie met initiatief-slug: 301 van /organisaties/{org} naar /{slug}', function () {
    $this->get(route('organisaties.show', ['slug' => 'carpe-vitam']))
        ->assertRedirect(route('initiatief.show', ['slug' => 'stekchallenge']));

    $this->get(route('initiatief.show', ['slug' => 'stekchallenge']))
        ->assertOk()
        ->assertSee('STEKchallenge: november is stekmaand', false)
        ->assertSee('Stekken en delen, overal in Nederland', false)
        ->assertSee('Doe je mee met STEKchallenge?', false)
        ->assertSee('Voor wie meedoet', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false);
});

test('organisaties-index en voor-organisaties linken naar /{slug} i.p.v. org-pagina', function () {
    $this->get(route('organisaties.index'))
        ->assertRedirect(route('partners').'#organisaties');

    $this->get(route('partners'))
        ->assertOk()
        ->assertSee(route('initiatief.show', ['slug' => 'stekchallenge']), false)
        ->assertSee(route('initiatief.show', ['slug' => 'struikroven']), false)
        ->assertSee(route('organisaties.show', ['slug' => 'velt']), false)
        ->assertDontSee('/organisaties/struikroven', false)
        ->assertDontSee('/organisaties/carpe-vitam"', false);

    $this->get(route('voor-organisaties'))
        ->assertOk()
        ->assertSee(route('initiatief.show', ['slug' => 'struikroven']), false)
        ->assertSee(route('initiatief.show', ['slug' => 'stekchallenge']), false);

});

test('sitemap: initiatief-URL i.p.v. /organisaties/{org} voor orgs met slug', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/struikroven')
        ->and($xml)->toContain('/stekchallenge')
        ->and($xml)->not->toContain('/organisaties/struikroven')
        ->and($xml)->not->toContain('/organisaties/carpe-vitam');
});

test('poster en flyer QR bron gebruiken initiatief-slug', function () {
    $a4 = $this->get(route('poster', ['voor' => 'stekchallenge']))->assertOk()->getContent();
    expect($a4)->toContain('data-qr-url="https://gratisplanten.nl/stekchallenge?bron=poster-stekchallenge"');

    $a5 = $this->get(route('poster', ['voor' => 'stekchallenge', 'formaat' => 'a5']))->assertOk()->getContent();
    expect($a5)->toContain('data-qr-url="https://gratisplanten.nl/stekchallenge?bron=flyer-stekchallenge"')
        ->and($a5)->toContain('poster--a5');
});

test('initiatiefvinkjes staan op het profiel, niet op registreren', function () {
    $this->get(route('account.registreren', ['stekchallenge' => 1]))
        ->assertOk()
        ->assertDontSee('name="stekchallenge"', false);

    $this->get(route('account.registreren', ['struikrover' => 1]))
        ->assertOk()
        ->assertDontSee('name="struikrover"', false);

    $user = \App\Models\User::factory()->create(['name' => 'Sam']);
    $html = $this->actingAs($user)->get(route('account.profiel'))->assertOk()->getContent();
    expect($html)->toContain('name="stekchallenge"')
        ->and($html)->toContain('name="struikrover"');
});

test('struikroven toont de secties uit de JSON en geen beheerscherm meer', function () {
    $this->get(route('organisaties.show', ['slug' => 'struikroven']))
        ->assertRedirect(route('struikroven'));

    $this->get(route('struikroven'))
        ->assertOk()
        ->assertSee('Wie zijn de Struikrovers?', false)
        ->assertSee('Struikroven en Gratis planten vullen elkaar aan', false)
        ->assertSee('Geredde plant thuis? Zo slaat hij aan', false);

    expect(Route::has('beheer.organisaties'))->toBeFalse();
});

test('org zonder initiatief-slug blijft op /organisaties/{slug}', function () {
    $this->get(route('organisaties.show', ['slug' => 'velt']))
        ->assertOk()
        ->assertSee('Velt', false);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/organisaties/velt');
});

test('Plantenasiel Haarlem heeft een rijke pagina zonder gratis ophalen bij het asiel', function () {
    $this->get(route('organisaties.show', ['slug' => 'plantenasiel-haarlem']))
        ->assertOk()
        ->assertSee('Een tweede thuis voor kamerplanten', false)
        ->assertSee('Jan Sluyterslaan 11, 2033 TM Haarlem', false)
        ->assertSee('Dinsdagavond van 19.00 tot 21.00 uur', false)
        ->assertSee('vrijwillige bijdrage', false)
        ->assertSee('<h2>Een plant aanbieden</h2>', false)
        ->assertSee('https://www.facebook.com/plantenasielhaarlem/', false)
        ->assertSee('https://www.instagram.com/plantenasielhaarlem/', false)
        ->assertSee(route('aanbieden'), false)
        ->assertDontSee('planten brengen en ophalen', false)
        ->assertDontSee('gratis ophalen', false)
        ->assertDontSee('gratis planten ophalen', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false);
});
