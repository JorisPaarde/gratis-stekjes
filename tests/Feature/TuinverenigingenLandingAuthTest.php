<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Models\User;
use App\Models\Vereniging;
use App\Notifications\VerifyEmailDutch;
use App\Support\Geo;
use App\Support\SafeIntended;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function tuinAuthPassword(): string
{
    return implode('-', ['TestOnly', 'Auth', 'Passphrase', '9']);
}

function tuinAuthForm(array $extra = []): array
{
    return array_merge([
        'website' => '',
        'form_started' => time() - 10,
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ], $extra);
}

function seedTuinRegGeo(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011 AB')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
}

test('tuinverenigingen landing: hiërarchie belofte→account→CTA vóór foto; stappen; attributie; geen we/wij', function () {
    $html = $this->get(route('tuinverenigingen'))
        ->assertOk()
        ->assertSee('Voor tuinverenigingen', false)
        ->assertSee('<h1 class="page-title">Geef plantmateriaal door met jullie tuinvereniging</h1>', false)
        ->assertSee('Een openbare pagina met het aanbod van jullie leden. Leden plaatsen zelf; het bestuur deelt de link.', false)
        ->assertSee('Laat een bestuurslid of ander lid de vereniging toevoegen met een gratis persoonlijk account. Je koppelt hiermee ook jezelf als lid. Heb je al een account? Log dan in.', false)
        ->assertSee('Bij een nieuw account bevestig je eerst je e-mailadres.', false)
        ->assertSee('Voeg jullie vereniging toe', false)
        ->assertSee('Maak een eigen account', false)
        ->assertSee('Inloggen', false)
        ->assertSee('Maak gratis een persoonlijk account of log in', false)
        ->assertSee('Deel de link met de leden', false)
        ->assertSee('Toon bij mijn aanbod dat ik hier lid van ben', false)
        ->assertSee(route('account.verenigingen', absolute: false), false)
        ->assertSee('next='.urlencode('/account/verenigingen'), false)
        ->assertSee(route('volkstuinen', absolute: false), false)
        ->assertSee('dreef-1280.webp', false)
        ->assertSee('dreef-640.webp', false)
        ->assertSee('dreef-1920.webp', false)
        ->assertSee('Gouwenaar / Wikimedia Commons', false)
        ->assertSee('https://commons.wikimedia.org/wiki/File:20180907_Volkstuin_bij_de_Dreef_Enkhuizen.jpg', false)
        ->assertSee('https://creativecommons.org/licenses/by-sa/4.0/', false)
        ->assertSee('CC BY-SA 4.0', false)
        ->assertDontSee('automatisch bij Mijn verenigingen', false)
        ->assertDontSee('uitsnede', false)
        ->assertDontSee('stijlgids', false)
        ->assertDontSee('OpenRouter', false)
        ->assertDontSee('deelplek-bridge', false)
        ->assertDontSee('carousel', false)
        ->getContent();

    // Hiërarchie: compacte belofte + CTA vóór hero-foto.
    $promisePos = strpos($html, 'Een openbare pagina met het aanbod van jullie leden');
    $ctaPos = strpos($html, 'Voeg jullie vereniging toe');
    $heroPos = strpos($html, 'audience-hero');
    $creditPos = strpos($html, 'Gouwenaar / Wikimedia Commons');
    $stepsPos = strpos($html, 'Zo doen jullie mee');
    expect($promisePos)->toBeInt()->and($ctaPos)->toBeInt()->and($heroPos)->toBeInt()
        ->and($creditPos)->toBeInt()->and($stepsPos)->toBeInt();
    expect($promisePos)->toBeLessThan($ctaPos)
        ->and($ctaPos)->toBeLessThan($heroPos)
        ->and($heroPos)->toBeLessThan($creditPos)
        ->and($creditPos)->toBeLessThan($stepsPos);

    expect(is_file(public_path('assets/images/tuinverenigingen/dreef-640.webp')))->toBeTrue()
        ->and(is_file(public_path('assets/images/tuinverenigingen/dreef-960.webp')))->toBeTrue()
        ->and(is_file(public_path('assets/images/tuinverenigingen/dreef-1280.webp')))->toBeTrue()
        ->and(is_file(public_path('assets/images/tuinverenigingen/dreef-1920.webp')))->toBeTrue()
        ->and(is_file(public_path('assets/images/tuinverenigingen/dreef-1280.jpg')))->toBeTrue();

    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    expect(preg_match('/\b(we|wij|ons|onze)\b/iu', $text))->toBe(0);
});

test('tuinverenigingen staat in sitemap en reservedSlugs; niet in menu/footer', function () {
    expect(\App\Services\InitiatiefPaginas::reservedSlugs())->toContain('tuinverenigingen');

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/tuinverenigingen');

    $home = $this->get('/')->assertOk()->getContent();
    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $home, $nav))->toBe(1);
    expect(preg_match('/<nav class="footer-links"[^>]*>(.*?)<\/nav>/s', $home, $footer))->toBe(1);
    expect($nav[1])->not->toContain('tuinverenigingen');
    expect($footer[1])->not->toContain('tuinverenigingen');
});

test('gast CTA /account/verenigingen → bestaande login → Mijn verenigingen', function () {
    $plain = tuinAuthPassword();
    $user = User::factory()->create([
        'email' => 'tuin-login@example.com',
        'password' => $plain,
        'email_verified_at' => now(),
        'welcome_shown_at' => now()->subDay(),
    ]);

    $this->get(route('account.verenigingen'))
        ->assertRedirect(route('account.inloggen'));

    // Laravel auth middleware stores the full intended URL; path must be Mijn verenigingen.
    $intended = (string) session(SafeIntended::SESSION_KEY);
    expect($intended)->toContain('/account/verenigingen');

    $this->post(route('account.inloggen'), tuinAuthForm([
        'email' => $user->email,
        'password' => $plain,
        'next' => '/account',
    ]))->assertRedirect('/account/verenigingen');

    $this->assertAuthenticatedAs($user);
    $this->get(route('account.verenigingen'))->assertOk();
});

test('nieuw register met next → signed mail-URL → verse sessie landt op Mijn verenigingen', function () {
    Notification::fake();
    Http::fake();
    seedTuinRegGeo();

    $this->get(route('account.registreren', ['next' => SafeIntended::ASSOCIATION]))
        ->assertOk()
        ->assertSee('name="next"', false)
        ->assertSee('value="'.SafeIntended::ASSOCIATION.'"', false);

    $this->post(route('account.registreren'), tuinAuthForm([
        'email' => 'tuin-nieuw@example.com',
        'password' => tuinAuthPassword(),
        'next' => SafeIntended::ASSOCIATION,
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(session(SafeIntended::SESSION_KEY))->toBe(SafeIntended::ASSOCIATION)
        ->and(session(SafeIntended::ASSOCIATION_RESEND_HINT))->toBeNull();

    // Nieuw register: werkende first-mail; géén resend-hint (zou token ongeldig maken).
    $this->get(route('account.bevestiging-nodig'))
        ->assertOk()
        ->assertDontSee('Gebruik alleen de meest recente link', false)
        ->assertDontSee('Wil je daarna naar', false);

    $user = User::query()->where('email', 'tuin-nieuw@example.com')->firstOrFail();
    Notification::assertSentTo($user, VerifyEmailDutch::class);

    /** @var VerifyEmailDutch $notification */
    $notification = Notification::sent($user, VerifyEmailDutch::class)->first();
    $url = $notification->toMail($user)->actionUrl;
    expect($url)->toContain(SafeIntended::ASSOCIATION_FLAG.'=1')
        ->and($url)->toContain('signature=');

    // Andere browser / mail-app: geen originating session.
    $this->flushSession();
    $this->assertGuest();
    expect(session(SafeIntended::SESSION_KEY))->toBeNull();

    $this->get($url)->assertRedirect(SafeIntended::ASSOCIATION);
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->welcome_shown_at)->not->toBeNull();
});

test('signed vereniging-flag zonder handtekening of met vervalsing wordt geweigerd', function () {
    $user = User::factory()->unverified()->create(['email' => 'tuin-tamper@example.com']);
    $token = $user->rotateEmailVerificationToken();

    $unsigned = URL::route('account.bevestigen', [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
        SafeIntended::ASSOCIATION_FLAG => '1',
    ]);
    $this->get($unsigned)
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHasErrors('email');

    $signed = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);
    // Flag later plakken → handtekening ongeldig.
    $tampered = $signed.(str_contains($signed, '?') ? '&' : '?').SafeIntended::ASSOCIATION_FLAG.'=1';
    $this->get($tampered)
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHasErrors('email');
});

test('register met intended /aanbieden behoudt welkom; Continue → /account (geen algemene intended)', function () {
    Notification::fake();
    Http::fake();
    seedTuinRegGeo();

    $this->withSession([SafeIntended::SESSION_KEY => '/aanbieden'])
        ->post(route('account.registreren'), tuinAuthForm([
            'email' => 'tuin-aanbieden@example.com',
            'password' => tuinAuthPassword(),
            'next' => '/aanbieden',
        ]))
        ->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'tuin-aanbieden@example.com')->firstOrFail();
    /** @var VerifyEmailDutch $notification */
    $notification = Notification::sent($user, VerifyEmailDutch::class)->first();
    $url = $notification->toMail($user)->actionUrl;
    expect($url)->not->toContain(SafeIntended::ASSOCIATION_FLAG.'=1');

    $this->flushSession();
    $this->get($url)->assertRedirect(route('account.welkom'));
    expect($user->fresh()->welcome_shown_at)->toBeNull();

    $this->get(route('account.welkom'))->assertOk();
    expect($user->fresh()->welcome_shown_at)->not->toBeNull();

    // Oude onboarding: Continue negeert willekeurige intended (ook /aanbieden).
    $this->withSession([SafeIntended::SESSION_KEY => '/aanbieden'])
        ->get(route('account.welkom.verder'))
        ->assertRedirect(route('account.index'));
});

test('welkom Continue met exact association-intended → Mijn verenigingen', function () {
    $user = User::factory()->create([
        'email' => 'tuin-welkom-vereniging@example.com',
        'email_verified_at' => now(),
        'welcome_shown_at' => null,
    ]);

    $this->actingAs($user)
        ->withSession([SafeIntended::SESSION_KEY => SafeIntended::ASSOCIATION])
        ->get(route('account.welkom.verder'))
        ->assertRedirect(SafeIntended::ASSOCIATION);

    expect($user->fresh()->welcome_shown_at)->not->toBeNull();
});

test('gedeelde audience-serienav bevat geen tuinverenigingen; landing toont bestaande serie', function () {
    $volkstuinen = $this->get(route('volkstuinen'))->assertOk()->getContent();
    expect(preg_match('/<nav class="audience-series"[^>]*>(.*?)<\/nav>/s', $volkstuinen, $nav))->toBe(1);
    // Huidige pagina is <span>, geen self-href; wel label + overige links.
    expect($nav[1])->not->toContain('/tuinverenigingen')
        ->and($nav[1])->toContain('Volkstuinverenigingen')
        ->and($nav[1])->toContain('/help-mee')
        ->and($nav[1])->toContain('/natuur');

    $landing = $this->get(route('tuinverenigingen'))->assertOk()->getContent();
    expect(preg_match('/<nav class="audience-series"[^>]*>(.*?)<\/nav>/s', $landing, $nav2))->toBe(1);
    expect($nav2[1])->not->toContain('/tuinverenigingen')
        ->and($nav2[1])->toContain('/volkstuinen')
        ->and($nav2[1])->toContain('/help-mee')
        ->and($nav2[1])->toContain('/natuur')
        ->and($nav2[1])->toContain('/verbinding');
});

test('VerifyEmailDutch toMail zonder session store (AccountSecurity/MailLog-pad)', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'safe-intended-nosession@example.com',
    ]);
    $user->rotateEmailVerificationToken();

    // Geen withSession: request heeft geen session store (zoals in mail-render tests).
    $mail = (new VerifyEmailDutch)->toMail($user);
    expect($mail->actionUrl)->toContain('/account/bevestigen/')
        ->and($mail->actionUrl)->not->toContain(SafeIntended::ASSOCIATION_FLAG.'=1')
        ->and($mail->actionUrl)->toContain('signature=');
});

test('oude bevestigingsmail zonder vereniging-flag in verse browser → welkom (niet association)', function () {
    $user = User::factory()->unverified()->create(['email' => 'tuin-old-mail@example.com']);
    $token = $user->rotateEmailVerificationToken();
    $oldUrl = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);
    expect($oldUrl)->not->toContain(SafeIntended::ASSOCIATION_FLAG.'=1');

    $this->flushSession();
    $this->get($oldUrl)->assertRedirect(route('account.welkom'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('bestaand onbevestigd: login association → uitleg → bewuste resend → verse browser → Mijn verenigingen', function () {
    Notification::fake();
    $plain = tuinAuthPassword();
    $user = User::factory()->unverified()->create([
        'email' => 'tuin-unverified-resend@example.com',
        'password' => $plain,
    ]);
    $user->rotateEmailVerificationToken();

    $this->get(route('account.inloggen', ['next' => SafeIntended::ASSOCIATION]))->assertOk();
    $this->post(route('account.inloggen'), tuinAuthForm([
        'email' => $user->email,
        'password' => $plain,
        'next' => SafeIntended::ASSOCIATION,
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(session(SafeIntended::SESSION_KEY))->toBe(SafeIntended::ASSOCIATION)
        ->and(session(SafeIntended::ASSOCIATION_RESEND_HINT))->toBeTrue();
    $this->assertGuest();

    $this->get(route('account.bevestiging-nodig'))
        ->assertOk()
        ->assertSee('Mijn verenigingen', false)
        ->assertSee('Gebruik alleen de meest recente link', false)
        ->assertSee('Stuur opnieuw', false)
        ->assertSee('niet automatisch', false);
    // Hint overleeft reload.
    $this->get(route('account.bevestiging-nodig'))
        ->assertOk()
        ->assertSee('Gebruik alleen de meest recente link', false);

    // Zelfde browser (intended nog in session): gebruiker kiest «Stuur opnieuw».
    $this->post(route('account.bevestiging-opnieuw'), [
        'email' => $user->email,
    ])->assertRedirect();

    $user->refresh();
    /** @var VerifyEmailDutch $notification */
    $notification = Notification::sent($user, VerifyEmailDutch::class)->first();
    expect($notification)->not->toBeNull();
    $newUrl = $notification->toMail($user)->actionUrl;
    expect($newUrl)->toContain(SafeIntended::ASSOCIATION_FLAG.'=1')
        ->and($newUrl)->toContain('signature=');

    // Verse browser: signed flag, geen originating session.
    $this->flushSession();
    $this->assertGuest();
    $this->get($newUrl)->assertRedirect(SafeIntended::ASSOCIATION);
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('zelfde browser: onbevestigde association-login → nieuw register → géén resend-hint; first-mail blijft geldig', function () {
    Notification::fake();
    Http::fake();
    seedTuinRegGeo();

    $plain = tuinAuthPassword();
    $oud = User::factory()->unverified()->create([
        'email' => 'tuin-oud-login@example.com',
        'password' => $plain,
    ]);

    $this->post(route('account.inloggen'), tuinAuthForm([
        'email' => $oud->email,
        'password' => $plain,
        'next' => SafeIntended::ASSOCIATION,
    ]))->assertRedirect(route('account.bevestiging-nodig'));
    expect(session(SafeIntended::ASSOCIATION_RESEND_HINT))->toBeTrue();

    // Inloggen → Account maken → nieuw adres (zelfde sessie behoudt anders de hint).
    $this->post(route('account.registreren'), tuinAuthForm([
        'email' => 'tuin-nieuw-na-login@example.com',
        'password' => $plain,
        'next' => SafeIntended::ASSOCIATION,
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(session(SafeIntended::ASSOCIATION_RESEND_HINT))->toBeNull()
        ->and(session(SafeIntended::SESSION_KEY))->toBe(SafeIntended::ASSOCIATION);

    $this->get(route('account.bevestiging-nodig'))
        ->assertOk()
        ->assertDontSee('Gebruik alleen de meest recente link', false)
        ->assertDontSee('Wil je daarna naar', false);

    $nieuw = User::query()->where('email', 'tuin-nieuw-na-login@example.com')->firstOrFail();
    Notification::assertSentTo($nieuw, VerifyEmailDutch::class);
    /** @var VerifyEmailDutch $notification */
    $notification = Notification::sent($nieuw, VerifyEmailDutch::class)->first();
    $url = $notification->toMail($nieuw)->actionUrl;
    expect($url)->toContain(SafeIntended::ASSOCIATION_FLAG.'=1');

    $this->flushSession();
    $this->get($url)->assertRedirect(SafeIntended::ASSOCIATION);
    expect($nieuw->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('gewoon onbevestigd inloggen zonder association: géén resend-hint', function () {
    $plain = tuinAuthPassword();
    $user = User::factory()->unverified()->create([
        'email' => 'tuin-ordinary-unverified@example.com',
        'password' => $plain,
    ]);

    $this->post(route('account.inloggen'), tuinAuthForm([
        'email' => $user->email,
        'password' => $plain,
        'next' => '/account',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(session(SafeIntended::ASSOCIATION_RESEND_HINT))->toBeNull();
    $this->get(route('account.bevestiging-nodig'))
        ->assertOk()
        ->assertDontSee('Gebruik alleen de meest recente link', false)
        ->assertDontSee('Wil je daarna naar', false);
});

test('al geverifieerde user met next landt op intended na login', function () {
    $plain = tuinAuthPassword();
    $user = User::factory()->create([
        'email' => 'tuin-verified@example.com',
        'password' => $plain,
        'welcome_shown_at' => now()->subDay(),
    ]);

    $this->get(route('account.inloggen', ['next' => '/account/verenigingen']))
        ->assertOk();

    $this->post(route('account.inloggen'), tuinAuthForm([
        'email' => $user->email,
        'password' => $plain,
        'next' => '/account/verenigingen',
    ]))->assertRedirect('/account/verenigingen');
});

test('ongeldige en externe intended next worden geweigerd', function () {
    expect(SafeIntended::isAllowed('https://evil.example/phish'))->toBeFalse();
    expect(SafeIntended::isAllowed('//evil.example'))->toBeFalse();
    expect(SafeIntended::isAllowed('/\\evil'))->toBeFalse();
    expect(SafeIntended::sanitize('https://evil.example'))->toBe('/account');
    expect(SafeIntended::sanitize('//evil.example/x'))->toBe('/account');
    expect(SafeIntended::sanitize('/account/verenigingen'))->toBe('/account/verenigingen');

    $local = rtrim((string) config('app.url'), '/').'/account/verenigingen';
    expect(SafeIntended::isAllowed($local))->toBeTrue()
        ->and(SafeIntended::sanitize($local))->toBe('/account/verenigingen');

    $plain = tuinAuthPassword();
    $user = User::factory()->create([
        'email' => 'tuin-safe@example.com',
        'password' => $plain,
        'welcome_shown_at' => now()->subDay(),
    ]);

    $this->get(route('account.inloggen', ['next' => 'https://evil.example']))
        ->assertOk();

    $this->post(route('account.inloggen'), tuinAuthForm([
        'email' => $user->email,
        'password' => $plain,
        'next' => 'https://evil.example',
    ]))->assertRedirect('/account');

    $this->post(route('account.uitloggen'));

    Notification::fake();
    Http::fake();
    seedTuinRegGeo();

    $this->post(route('account.registreren'), tuinAuthForm([
        'email' => 'tuin-evil-next@example.com',
        'password' => tuinAuthPassword(),
        'next' => '//evil.example',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(session(SafeIntended::SESSION_KEY))->not->toBe('//evil.example');
});

test('standaard bevestiging zonder intended blijft naar welkom (anti-loop)', function () {
    $user = User::factory()->unverified()->create(['email' => 'tuin-welkom-default@example.com']);
    $token = $user->rotateEmailVerificationToken();
    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);

    $this->get($url)->assertRedirect(route('account.welkom'));
});

test('vereniging: bestaande kiezen, nieuwe pending, goedgekeurd deelt link; opt-in aanbod', function () {
    Notification::fake();
    config(['stekjes.admin_email' => 'beheer@example.com']);

    $bestuur = User::factory()->create([
        'email' => 'tuin-bestuur@example.com',
        'name' => 'Bestuur',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
    $lid = User::factory()->create([
        'email' => 'tuin-lid@example.com',
        'name' => 'Lid',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);

    $this->actingAs($bestuur)
        ->post(route('account.verenigingen.store'), [
            'vereniging' => 'Tuinvereniging De Landing',
            'plaats' => 'Haarlem',
            'type' => 'tuinvereniging',
        ])
        ->assertRedirect(route('account.verenigingen'));

    $v = Vereniging::query()->firstOrFail();
    expect($v->status)->toBe('pending')
        ->and((bool) $bestuur->verenigingen()->first()->pivot->zichtbaar)->toBeFalse();

    $this->actingAs($bestuur)->get(route('account.verenigingen'))
        ->assertOk()
        ->assertSee('Wacht op controle', false);

    $this->get(route('organisaties.show', ['slug' => $v->slug]))->assertNotFound();

    $v->update(['status' => 'approved']);

    $html = $this->actingAs($bestuur)->get(route('account.verenigingen'))
        ->assertOk()
        ->assertSee(route('organisaties.show', ['slug' => $v->slug], absolute: false), false)
        ->getContent();
    expect($html)->toContain('/organisaties/'.$v->slug);

    $this->actingAs($lid)
        ->post(route('account.verenigingen.store'), [
            'vereniging' => 'Tuinvereniging De Landing — Haarlem',
        ])
        ->assertRedirect(route('account.verenigingen'));

    expect(Vereniging::query()->count())->toBe(1)
        ->and($v->fresh()->leden()->count())->toBe(2);

    $aanbod = \App\Models\Aanbod::query()->create([
        'user_id' => $lid->id,
        'type' => 'uitdelen',
        'tekst' => 'Tuinlanding munt',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    // Zonder opt-in: niet op verenigingspagina.
    $page = $this->get(route('organisaties.show', ['slug' => $v->slug]))->assertOk()->getContent();
    expect($page)->not->toContain('Tuinlanding munt');

    $this->actingAs($lid)
        ->post(route('account.verenigingen.update', $v), ['zichtbaar' => '1'])
        ->assertRedirect();

    $page = $this->get(route('organisaties.show', ['slug' => $v->slug]))->assertOk()->getContent();
    expect($page)->toContain('Tuinlanding munt');

    expect($aanbod->fresh()->id)->toBeInt();
});
