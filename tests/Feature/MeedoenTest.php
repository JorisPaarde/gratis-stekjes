<?php

declare(strict_types=1);

use App\Http\Controllers\MeedoenController;
use App\Notifications\PartnerAanvraag;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

function partnerData(array $extra = []): array
{
    return array_merge([
        'naam' => 'Sanne',
        'organisatie' => 'Bieb Zuid',
        'soort' => 'bieb',
        'email' => 'sanne@example.com',
        'bericht' => 'Wij willen graag een poster en een plantenbieb.',
        'form_started' => time() - 30,
        'website' => '',
    ], $extra);
}

beforeEach(function () {
    RateLimiter::clear('partner:127.0.0.1');
});

test('help-mee toont posters, deelberichten, maker en partnerformulier', function () {
    $html = $this->get(route('help-mee'))->assertOk()->getContent();
    expect($html)->toContain(route('poster', ['voor' => 'volkstuin']))
        ->and($html)->toContain(route('stekjeslabels'))
        ->and($html)->toContain('data-kopieer="bericht-0"')
        ->and($html)->toContain('facebook.com/sharer/sharer.php')
        ->and($html)->toContain('wa.me/?text=')
        ->and($html)->toContain('linkedin.com/sharing/share-offsite')
        ->and($html)->toContain('Hoi, ik ben '.config('site.maker'))
        ->and($html)->toContain(route('over'))
        ->and($html)->toContain('id="partner"')
        ->and($html)->toContain(route('help-mee.partner'));
});

test('volg-mij toont alleen ingestelde socials', function () {
    config(['site.socials' => []]);
    $this->get(route('help-mee'))->assertDontSee('Volg mij', false);
    config(['site.socials' => ['Instagram' => 'https://instagram.com/voorbeeld']]);
    $this->get(route('help-mee'))->assertSee('Volg mij', false)->assertSee('https://instagram.com/voorbeeld', false);
});

test('poster-varianten tonen QR naar de juiste pagina en zijn noindex', function () {
    foreach (array_keys(MeedoenController::POSTERS) as $voor) {
        $html = $this->get(route('poster', ['voor' => $voor]))->assertOk()->getContent();
        expect($html)->toContain('data-qr-url=')
            ->and($html)->toContain('bron=poster-'.$voor)
            ->and($html)->toContain('noindex');
    }
    $this->get(route('poster', ['voor' => 'onzin']))->assertOk()->assertSee('bron=poster-algemeen', false);
});

test('stekjeslabels: 8 labels met QR', function () {
    $html = $this->get(route('stekjeslabels'))->assertOk()->getContent();
    expect(substr_count($html, 'class="label"'))->toBe(8)
        ->and($html)->toContain('bron=label');
});

test('partneraanvraag mailt beheer', function () {
    Notification::fake();
    config(['stekjes.admin_email' => 'beheer@example.com']);

    $this->post(route('help-mee.partner'), partnerData())
        ->assertRedirect(route('help-mee').'#partner')
        ->assertSessionHas('partner_status');

    Notification::assertSentOnDemand(PartnerAanvraag::class);
});

test('partneraanvraag: validatie, honeypot en te snel', function () {
    Notification::fake();
    config(['stekjes.admin_email' => 'beheer@example.com']);

    $this->post(route('help-mee.partner'), partnerData(['email' => 'geen-mail', 'soort' => 'x']))
        ->assertSessionHasErrors(['email', 'soort']);
    $this->post(route('help-mee.partner'), partnerData(['website' => 'http://spam.example']))
        ->assertSessionHas('partner_status');
    $this->post(route('help-mee.partner'), partnerData(['form_started' => time()]))
        ->assertSessionHasErrors('partner');

    Notification::assertNothingSent();
});

test('partneraanvraag: max 3 per uur', function () {
    Notification::fake();
    config(['stekjes.admin_email' => 'beheer@example.com']);
    foreach (range(1, 3) as $i) {
        $this->post(route('help-mee.partner'), partnerData())->assertSessionHas('partner_status');
    }
    $this->post(route('help-mee.partner'), partnerData())->assertSessionHasErrors('partner');
});
