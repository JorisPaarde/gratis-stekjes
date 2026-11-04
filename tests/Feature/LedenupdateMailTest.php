<?php

declare(strict_types=1);

use App\Mail\LedenupdateMail;
use App\Models\LedenupdateMailSent;
use App\Models\User;
use App\Support\LedenupdateMailContent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

function ledenupdateUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Sam',
        'locatie_label' => '',
        'postcode_gebied' => null,
        'woonplaats' => null,
        'lat' => null,
        'lon' => null,
        'country' => 'NL',
        'active' => true,
        'email_verified_at' => now(),
    ], $extra));
}

test('ledenupdate body: postcode-zin alleen zonder geldige postcode, tier via MilestoneService', function () {
    $zonder = ledenupdateUser(['name' => 'Ada', 'email' => 'ada-lu@example.com']);
    $met = ledenupdateUser([
        'name' => 'Ben',
        'email' => 'ben-lu@example.com',
        'postcode_gebied' => '2037',
        'woonplaats' => 'Haarlem',
        'locatie_label' => '2037 Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
    ]);

    $ms = app(\App\Services\MilestoneService::class);
    $dataZonder = LedenupdateMailContent::placeholders($zonder, $ms);
    $dataMet = LedenupdateMailContent::placeholders($met, $ms);

    expect($dataZonder)->not->toBeNull()
        ->and($dataZonder['nr'])->toBe(1)
        ->and($dataZonder['tier'])->toBe(10)
        ->and($dataZonder['vraag_postcode'])->toBeTrue()
        ->and($dataMet['nr'])->toBe(2)
        ->and($dataMet['tier'])->toBe(10)
        ->and($dataMet['vraag_postcode'])->toBeFalse();

    $bodyZonder = LedenupdateMailContent::plainBody($dataZonder);
    $bodyMet = LedenupdateMailContent::plainBody($dataMet);

    expect($bodyZonder)->toContain('Hoi Ada,')
        ->and($bodyZonder)->toContain('lid nummer 1, een van de eerste 10')
        ->and($bodyZonder)->toContain('Vul je postcode in, dan zie je wat er bij jou in de buurt is.')
        ->and($bodyZonder)->toContain('https://gratisplanten.nl/account/profiel')
        ->and($bodyMet)->toContain('Hoi Ben,')
        ->and($bodyMet)->toContain('lid nummer 2, een van de eerste 10')
        ->and($bodyMet)->not->toContain('Vul je postcode in');
});

test('gp:ledenupdate-mail dry-run faalt bij lege naam en toont geen e-mail', function () {
    $ok = ledenupdateUser(['name' => 'Oké', 'email' => 'ok-lu@example.com']);
    $leeg = ledenupdateUser(['name' => '   ', 'email' => 'leeg-lu@example.com']);

    $exit = Artisan::call('gp:ledenupdate-mail', ['--dry-run' => true]);
    $out = Artisan::output();

    expect($exit)->toBe(1)
        ->and($out)->toContain('#'.$leeg->id.' · lidnummer 2 · tier 10 · postcode-zin ja · aanhef - · BLOKKEREND: mist naam')
        ->and($out)->toContain('#'.$ok->id.' · lidnummer 1 · tier 10 · postcode-zin ja · aanhef naam')
        ->and($out)->toContain('Dry-run mislukt')
        ->and($out)->not->toContain('ok-lu@example.com')
        ->and($out)->not->toContain('leeg-lu@example.com')
        ->and($out)->not->toContain((string) $ok->email);
});

test('gp:ledenupdate-mail dry-run toont gerenderde body zonder e-mail of straat', function () {
    $u = ledenupdateUser([
        'name' => 'Pia',
        'email' => 'pia-geheim@example.com',
        'adres_straat' => 'Geheimstraat',
        'adres_huisnummer' => '1',
        'adres_postcode' => '2037AB',
        'adres_plaats' => 'Haarlem',
    ]);

    $exit = Artisan::call('gp:ledenupdate-mail');
    $out = Artisan::output();

    expect($exit)->toBe(0)
        ->and($out)->toContain('#'.$u->id)
        ->and($out)->toContain('lidnummer 1 · tier 10 · postcode-zin ja · aanhef naam')
        ->and($out)->toContain('Onderwerp: '.LedenupdateMailContent::SUBJECT)
        ->and($out)->toContain('Hoi [naam],')
        ->and($out)->not->toContain('Pia')
        ->and($out)->toContain('Lid-id\'s: '.$u->id)
        ->and($out)->not->toContain('pia-geheim@example.com')
        ->and($out)->not->toContain('Geheimstraat')
        ->and($out)->not->toContain('2037AB');
});

test('gp:ledenupdate-mail --ids print kommagescheiden ids zonder e-mail', function () {
    $a = ledenupdateUser(['email' => 'a-ids@example.com']);
    $b = ledenupdateUser(['email' => 'b-ids@example.com']);
    ledenupdateUser(['email' => 'inactive-ids@example.com', 'active' => false]);
    User::factory()->statsViewer()->create(['email' => 'stats-ids@example.com']);

    Artisan::call('gp:ledenupdate-mail', ['--ids' => true]);
    $out = trim(Artisan::output());

    expect($out)->toBe($a->id.', '.$b->id)
        ->and($out)->not->toContain('@');
});

test('gp:ledenupdate-mail --send is idempotent en gebruikt Mail::fake', function () {
    Mail::fake();

    $u = ledenupdateUser(['email' => 'send-lu@example.com', 'name' => 'Kim']);

    expect(Artisan::call('gp:ledenupdate-mail', ['--send' => true, '--id' => (string) $u->id]))->toBe(0);
    Mail::assertSent(LedenupdateMail::class, 1);
    expect(LedenupdateMailSent::query()->where('user_id', $u->id)->where('status', 'sent')->exists())->toBeTrue();

    expect(Artisan::call('gp:ledenupdate-mail', ['--send' => true, '--id' => (string) $u->id]))->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain('al verstuurd')
        ->and($out)->not->toContain('send-lu@example.com');
    Mail::assertSent(LedenupdateMail::class, 1);
});

test('gp:ledenupdate-mail --send weigert id buiten ontvangersset', function () {
    Mail::fake();
    $inactief = ledenupdateUser(['email' => 'out-lu@example.com', 'active' => false]);

    expect(Artisan::call('gp:ledenupdate-mail', ['--send' => true, '--id' => (string) $inactief->id]))->toBe(1);
    Mail::assertNothingSent();
});

test('deploy ledenupdate-mail endpoint vereist token en ondersteunt modes', function () {
    config(['stekjes.deploy_token' => 'test-token', 'stekjes.deploy_endpoint_enabled' => true]);
    RateLimiter::clear('deploy-ledenupdate-mail:127.0.0.1');
    Mail::fake();
    $u = ledenupdateUser(['email' => 'deploy-lu@example.com']);

    $this->postJson('/deploy/ledenupdate-mail', ['mode' => 'dry-run'], [
        'Authorization' => 'Bearer test-token',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('mode', 'dry-run')
        ->assertJsonStructure(['output', 'at']);

    $ids = $this->postJson('/deploy/ledenupdate-mail', ['mode' => 'ids'], [
        'Authorization' => 'Bearer test-token',
    ])->assertOk()->json('output');
    expect(trim((string) $ids))->toContain((string) $u->id)
        ->and($ids)->not->toContain('deploy-lu@example.com');

    $this->postJson('/deploy/ledenupdate-mail', ['mode' => 'send', 'id' => $u->id], [
        'Authorization' => 'Bearer test-token',
    ])->assertOk()->assertJsonPath('ok', true);
    Mail::assertSent(LedenupdateMail::class, 1);

    $this->postJson('/deploy/ledenupdate-mail', ['mode' => 'dry-run'])->assertForbidden();
});

test('LedenupdateMail mailable subject en from via MAIL_*', function () {
    config([
        'mail.from.address' => 'info@gratisplanten.nl',
        'mail.from.name' => 'Gratis planten',
    ]);
    $mail = new LedenupdateMail([
        'naam' => 'Sam',
        'nr' => 3,
        'tier' => 10,
        'vraag_postcode' => false,
    ]);

    expect($mail->envelope()->subject)->toBe(LedenupdateMailContent::SUBJECT)
        ->and($mail->envelope()->from->address)->toBe('info@gratisplanten.nl')
        ->and($mail->envelope()->from->name)->toBe('Gratis planten');
});

test('voornaamUitEmail leidt voornaam af of geeft null', function (string $email, ?string $verwacht) {
    expect(LedenupdateMailContent::voornaamUitEmail($email))->toBe($verwacht);
})->with([
    ['jan.devries@example.com', 'Jan'],
    ['marieke123@example.com', 'Marieke'],
    ['ANNA_b@example.com', 'Anna'],
    ['piet-jan@example.com', 'Piet'],
    ['kees+gp@example.com', 'Kees'],
    ['12sanne@example.com', 'Sanne'],
    ['info@example.com', null],
    ['contact@example.com', null],
    ['noreply@example.com', null],
    ['webmaster@example.com', null],
    ['hallo@example.com', null],
    ['12345@example.com', null],
    ['j.devries@example.com', null],
    ['pvd@example.com', null],
    ['jk76@example.com', null],
]);

test('ledenupdate: e-mail als naam → afgeleide voornaam, niet in output, DB ongewijzigd', function () {
    $u = ledenupdateUser(['name' => 'jan.devries@example.com', 'email' => 'jan.devries@example.com']);

    $data = LedenupdateMailContent::placeholders($u, app(\App\Services\MilestoneService::class));
    expect($data['naam'])->toBe('Jan')
        ->and($data['aanhef'])->toBe(LedenupdateMailContent::AANHEF_AFGELEID)
        ->and(LedenupdateMailContent::plainBody($data))->toStartWith("Hoi Jan,\n");

    $exit = Artisan::call('gp:ledenupdate-mail', ['--dry-run' => true]);
    $out = Artisan::output();

    expect($exit)->toBe(0)
        ->and($out)->toContain('#'.$u->id.' · lidnummer 1 · tier 10 · postcode-zin ja · aanhef afgeleid')
        ->and($out)->toContain('Hoi [naam],')
        ->and($out)->not->toContain('Jan')
        ->and($out)->not->toContain('jan.devries')
        ->and($out)->not->toContain('@');
    expect($u->fresh()->name)->toBe('jan.devries@example.com');
});

test('ledenupdate: e-mail als naam zonder bruikbare voornaam → «Hoi,»', function () {
    Mail::fake();
    $u = ledenupdateUser(['name' => 'info@example.com', 'email' => 'info@example.com']);

    $data = LedenupdateMailContent::placeholders($u, app(\App\Services\MilestoneService::class));
    expect($data['naam'])->toBe('')
        ->and($data['aanhef'])->toBe(LedenupdateMailContent::AANHEF_ZONDER)
        ->and(LedenupdateMailContent::plainBody($data))->toStartWith("Hoi,\n");

    $exit = Artisan::call('gp:ledenupdate-mail', ['--dry-run' => true]);
    $out = Artisan::output();
    expect($exit)->toBe(0)
        ->and($out)->toContain('aanhef zonder naam')
        ->and($out)->toContain('  Hoi,')
        ->and($out)->not->toContain('@');

    expect(Artisan::call('gp:ledenupdate-mail', ['--send' => true, '--id' => (string) $u->id]))->toBe(0);
    Mail::assertSent(LedenupdateMail::class, fn ($m) => str_starts_with(LedenupdateMailContent::plainBody($m->data), "Hoi,\n"));
});

test('ledenupdate: normale naam blijft ongewijzigd', function () {
    $u = ledenupdateUser(['name' => 'Anouk', 'email' => 'iets.anders@example.com']);
    $data = LedenupdateMailContent::placeholders($u, app(\App\Services\MilestoneService::class));
    expect($data['naam'])->toBe('Anouk')->and($data['aanhef'])->toBe('naam');
});

test('ledenupdate tekstdeel bevat geen HTML-entities', function () {
    $mail = new LedenupdateMail(['naam' => 'Sam', 'nr' => 3, 'tier' => 10, 'vraag_postcode' => false]);
    $rendered = $mail->render();
    $text = view('mail.ledenupdate', ['body' => LedenupdateMailContent::plainBody($mail->data)])->render();

    expect($text)->toContain("Typ bijvoorbeeld 'vrouwentong'")
        ->and($text)->not->toContain('&#039;')
        ->and($rendered)->toContain('https://gratisplanten.nl/account/profiel');
});
