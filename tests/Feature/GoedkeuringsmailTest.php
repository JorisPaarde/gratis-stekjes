<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Models\GeocodeCache;
use App\Models\MailLog;
use App\Models\User;
use App\Models\Vereniging;
use App\Notifications\DeelplekGoedgekeurd;
use App\Notifications\VerenigingGoedgekeurd;
use App\Support\Geo;
use Illuminate\Support\Facades\Notification;

function goedkeuringLid(string $email, string $name = 'Sam'): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => $name,
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
}

function goedkeuringGeoUtrecht(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('Neude 1 3512 AD Utrecht')],
        [
            'lat' => 52.0929,
            'lon' => 5.1182,
            'label' => 'Neude, Utrecht',
            'cached_at' => now(),
        ]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('Utrecht')],
        [
            'lat' => 52.0907,
            'lon' => 5.1214,
            'label' => 'Utrecht',
            'cached_at' => now(),
        ]
    );
}

function goedkeuringAanmeldPayload(array $overrides = []): array
{
    return array_merge([
        'naam' => 'Goedgekeurde Ruilkast Utrecht',
        'type' => 'ruilkast',
        'country' => 'NL',
        'adres' => 'Neude 1',
        'postcode' => '3512 AD',
        'plaats' => 'Utrecht',
        'url' => 'https://example.org/ruilkast',
        'beschrijving' => 'Gratis stekjes.',
        'contact_email' => 'aanmelder@example.org',
        'privacy' => '1',
        'website' => '',
        'form_started' => time() - 10,
    ], $overrides);
}

test('eerste approve van vereniging stuurt mail aan maker en schrijft mail_log', function () {
    Notification::fake();
    config(['stekjes.admin_dashboard_emails' => ['admin@example.com']]);
    $admin = goedkeuringLid('admin@example.com', 'Joris');
    $maker = goedkeuringLid('maker@example.com', 'Maker');

    $this->actingAs($maker)->post(route('account.verenigingen.store'), [
        'vereniging' => 'Volkstuin De Goedkeuring',
        'plaats' => 'Haarlem',
        'type' => 'volkstuin',
    ])->assertRedirect();

    $v = Vereniging::query()->firstOrFail();
    expect($v->created_by)->toBe($maker->id)
        ->and($v->approval_mailed_at)->toBeNull();

    $this->actingAs($admin)
        ->post(route('beheer.verenigingen.status', $v), ['status' => 'approved'])
        ->assertRedirect();

    $v->refresh();
    expect($v->status)->toBe(Vereniging::STATUS_APPROVED)
        ->and($v->approval_mailed_at)->not->toBeNull();

    Notification::assertSentTo($maker, VerenigingGoedgekeurd::class, function (VerenigingGoedgekeurd $n) use ($v) {
        $mail = $n->toMail((object) []);

        return $n->vereniging->is($v)
            && $mail->subject === 'Je vereniging staat op Gratis planten: Volkstuin De Goedkeuring'
            && str_contains(collect($mail->introLines)->implode(' '), 'goedgekeurd')
            && str_contains((string) $mail->actionUrl, '/organisaties/'.$v->slug)
            && str_contains((string) $mail->salutation, (string) config('site.maker'));
    });

    $row = MailLog::query()->where('user_id', $maker->id)->where('type', 'VerenigingGoedgekeurd')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('ok');
});

test('tweede approve of hide-dan-approve van vereniging stuurt geen tweede mail', function () {
    Notification::fake();
    config(['stekjes.admin_dashboard_emails' => ['admin2@example.com']]);
    $admin = goedkeuringLid('admin2@example.com', 'Joris');
    $maker = goedkeuringLid('maker2@example.com', 'Maker');

    $this->actingAs($maker)->post(route('account.verenigingen.store'), [
        'vereniging' => 'Tuinpark Idempotent',
        'plaats' => 'Utrecht',
        'type' => 'tuinvereniging',
    ])->assertRedirect();
    $v = Vereniging::query()->firstOrFail();

    $this->actingAs($admin)->post(route('beheer.verenigingen.status', $v), ['status' => 'approved']);
    Notification::assertSentTo($maker, VerenigingGoedgekeurd::class);
    expect(MailLog::query()->where('type', 'VerenigingGoedgekeurd')->count())->toBe(1);

    Notification::fake();
    $this->actingAs($admin)->post(route('beheer.verenigingen.status', $v), ['status' => 'approved']);
    Notification::assertNothingSent();

    $this->actingAs($admin)->post(route('beheer.verenigingen.status', $v), ['status' => 'hidden']);
    Notification::fake();
    $this->actingAs($admin)->post(route('beheer.verenigingen.status', $v), ['status' => 'approved']);
    Notification::assertNothingSent();
    expect(MailLog::query()->where('type', 'VerenigingGoedgekeurd')->count())->toBe(1)
        ->and($v->fresh()->approval_mailed_at)->not->toBeNull();
});

test('approve van vereniging zonder maker stuurt geen mail', function () {
    Notification::fake();
    config(['stekjes.admin_dashboard_emails' => ['admin3@example.com']]);
    $admin = goedkeuringLid('admin3@example.com', 'Joris');

    $v = Vereniging::query()->create([
        'slug' => 'vaste-lijst-tuin-zonder-maker',
        'naam' => 'Vaste Lijst Tuin',
        'plaats' => 'Amsterdam',
        'type' => 'volkstuin',
        'json_slug' => 'vaste-lijst-tuin-zonder-maker',
        'status' => Vereniging::STATUS_PENDING,
        'created_by' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('beheer.verenigingen.status', $v), ['status' => 'approved'])
        ->assertRedirect(route('beheer.verenigingen'));

    expect($v->fresh()->status)->toBe(Vereniging::STATUS_APPROVED)
        ->and($v->fresh()->approval_mailed_at)->toBeNull();
    Notification::assertNothingSent();
    expect(MailLog::query()->where('type', 'VerenigingGoedgekeurd')->count())->toBe(0);
});

test('eerste approve van deelplek-aanmelding stuurt mail en schrijft mail_log', function () {
    Notification::fake();
    goedkeuringGeoUtrecht();

    $this->post(route('voor-organisaties.aanmelden'), goedkeuringAanmeldPayload())->assertRedirect();
    $plek = Deelplek::query()->where('naam', 'Goedgekeurde Ruilkast Utrecht')->firstOrFail();
    expect($plek->approval_mailed_at)->toBeNull();

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.deelplekken.approve'), ['id' => $plek->id])
        ->assertRedirect(route('beheer.aanmeldingen'));

    $plek->refresh();
    expect($plek->status)->toBe(Deelplek::STATUS_APPROVED)
        ->and($plek->visible)->toBeTrue()
        ->and($plek->approval_mailed_at)->not->toBeNull();

    Notification::assertSentOnDemand(DeelplekGoedgekeurd::class, function (DeelplekGoedgekeurd $n, $channels, $notifiable) use ($plek) {
        $mail = $n->toMail((object) []);

        return $notifiable->routes['mail'] === 'aanmelder@example.org'
            && $n->deelplek->is($plek)
            && $mail->subject === 'Je deelplek staat op Gratis planten: Goedgekeurde Ruilkast Utrecht'
            && str_contains(collect($mail->introLines)->implode(' '), 'goedgekeurd')
            && str_contains((string) $mail->actionUrl, '/deelplekken')
            && str_contains((string) $mail->salutation, (string) config('site.maker'));
    });

    $row = MailLog::query()->where('type', 'DeelplekGoedgekeurd')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('ok');
});

test('tweede approve van deelplek stuurt geen tweede mail', function () {
    Notification::fake();
    goedkeuringGeoUtrecht();

    $this->post(route('voor-organisaties.aanmelden'), goedkeuringAanmeldPayload([
        'naam' => 'Tweede Keer Ruilkast',
        'contact_email' => 'tweede@example.org',
    ]))->assertRedirect();
    $plek = Deelplek::query()->where('naam', 'Tweede Keer Ruilkast')->firstOrFail();

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.deelplekken.approve'), ['id' => $plek->id])
        ->assertRedirect();

    Notification::assertSentOnDemand(DeelplekGoedgekeurd::class);
    expect(MailLog::query()->where('type', 'DeelplekGoedgekeurd')->count())->toBe(1);

    Notification::fake();
    // Direct opnieuw via service (controller vindt alleen pending).
    app(\App\Services\DeelplekAanmelding::class)->approve($plek->fresh());
    Notification::assertNothingSent();
    expect(MailLog::query()->where('type', 'DeelplekGoedgekeurd')->count())->toBe(1)
        ->and($plek->fresh()->approval_mailed_at)->not->toBeNull();
});

test('goedkeuringsmails zijn ik-vorm zonder we/wij', function () {
    $v = new Vereniging(['naam' => 'Testvereniging', 'slug' => 'testvereniging']);
    $vMail = (new VerenigingGoedgekeurd($v))->toMail((object) []);
    $vLines = collect($vMail->introLines)->implode(' ');
    expect($vLines)->toContain('Ik heb')
        ->and($vLines)->not->toMatch('/\b[Ww]e\b/')
        ->and($vLines)->not->toMatch('/\b[Ww]ij\b/')
        ->and((string) $vMail->salutation)->toContain((string) config('site.maker'));

    $p = new Deelplek(['naam' => 'Testplek', 'plaats' => 'Utrecht']);
    $pMail = (new DeelplekGoedgekeurd($p))->toMail((object) []);
    $pLines = collect($pMail->introLines)->implode(' ');
    expect($pLines)->toContain('Ik heb')
        ->and($pLines)->not->toMatch('/\b[Ww]e\b/')
        ->and($pLines)->not->toMatch('/\b[Ww]ij\b/')
        ->and((string) $pMail->salutation)->toContain((string) config('site.maker'));
});

test('deelplek-goedkeuringsmail aan een lid komt in diens mail_log', function () {
    Notification::fake();
    goedkeuringGeoUtrecht();
    $lid = goedkeuringLid('lid-aanmelder@example.org', 'Lid');

    $this->post(route('voor-organisaties.aanmelden'), goedkeuringAanmeldPayload([
        'naam' => 'Ruilkast Van Een Lid',
        'contact_email' => 'lid-aanmelder@example.org',
    ]))->assertRedirect();
    $plek = Deelplek::query()->where('naam', 'Ruilkast Van Een Lid')->firstOrFail();

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.deelplekken.approve'), ['id' => $plek->id])
        ->assertRedirect();

    $row = MailLog::query()->where('type', 'DeelplekGoedgekeurd')->first();
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($lid->id)
        ->and($row->status)->toBe('ok');
});
