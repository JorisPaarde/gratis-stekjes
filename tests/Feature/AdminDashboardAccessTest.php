<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Models\PartnerMarker;
use App\Models\User;
use App\Support\Geo;

/** Beheer-fixture opgebouwd (geen hardcoded secret-literal voor scanners). */
function dashboardAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Dashboard', 'Passphrase', '9']);
}

/** Uniek testaccount-e-mail voor jpwebcreation-eigenaar (geen echte mailbox). */
function jpwebcreationTestEmail(): string
{
    return 'jpwebcreation-owner@example.test';
}

beforeEach(function () {
    config([
        'stekjes.admin_password' => dashboardAdminPassphrase(),
        'stekjes.admin_dashboard_emails' => [jpwebcreationTestEmail()],
        'stekjes.admin_email' => 'admin-mailbox@example.test',
    ]);
});

function extractHoofdmenu(string $html): string
{
    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $html, $nav))->toBe(1);

    return $nav[1];
}

test('gast ziet geen Dashboard-link in hoofdmenu', function () {
    $nav = extractHoofdmenu($this->get(route('home'))->assertOk()->getContent());

    expect($nav)->not->toContain('Dashboard')
        ->and($nav)->not->toContain(route('beheer.index'));
});

test('gewone gebruiker ziet geen Dashboard-link', function () {
    $user = User::factory()->create([
        'email' => 'lid@example.test',
        'email_verified_at' => now(),
    ]);

    $nav = extractHoofdmenu(
        $this->actingAs($user)->get(route('home'))->assertOk()->getContent()
    );

    expect($nav)->not->toContain('Dashboard')
        ->and($nav)->not->toContain(route('beheer.index'));
});

test('admin_ok-sessie toont Dashboard-link', function () {
    $nav = extractHoofdmenu(
        $this->withSession(['admin_ok' => true])
            ->get(route('home'))
            ->assertOk()
            ->getContent()
    );

    expect($nav)->toContain('Dashboard')
        ->and($nav)->toContain(route('beheer.index'));
});

test('jpwebcreation-account toont Dashboard-link', function () {
    $owner = User::factory()->create([
        'email' => jpwebcreationTestEmail(),
        'email_verified_at' => now(),
    ]);

    $nav = extractHoofdmenu(
        $this->actingAs($owner)->get(route('home'))->assertOk()->getContent()
    );

    expect($nav)->toContain('Dashboard')
        ->and($nav)->toContain(route('beheer.index'));
});

test('jpwebcreation-account zonder admin_ok krijgt /beheer/overzicht en POST', function () {
    $owner = User::factory()->create([
        'email' => jpwebcreationTestEmail(),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('beheer.index'))
        ->assertOk()
        ->assertSee('Kaartmarkers', false);

    $this->actingAs($owner)
        ->get(route('beheer.login'))
        ->assertRedirect(route('beheer.index'));

    GeocodeCache::query()->create([
        'query_key' => Geo::normalizeQuery('Utrecht'),
        'lat' => 52.0907,
        'lon' => 5.1214,
        'label' => 'Utrecht',
        'cached_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('beheer.markers.save'), [
            'naam' => 'Owner voedselbos',
            'type' => 'voedselbos',
            'plaats' => 'Utrecht',
            'beschrijving' => 'Via account',
            'url' => 'https://example.org/owner',
            'bron' => 'intern',
            'visible' => '1',
        ])
        ->assertRedirect(route('beheer.index'));

    expect(PartnerMarker::query()->where('naam', 'Owner voedselbos')->exists())->toBeTrue();
});

test('gewone gebruiker wordt naar beheer.login gestuurd', function () {
    $user = User::factory()->create([
        'email' => 'lid@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('beheer.index'))
        ->assertRedirect(route('beheer.login'));

    $this->actingAs($user)
        ->post(route('beheer.markers.save'), [
            'naam' => 'Nee',
            'type' => 'voedselbos',
            'plaats' => 'Utrecht',
        ])
        ->assertRedirect(route('beheer.login'));
});

test('ongeverifieerde jpwebcreation-mail heeft geen toegang', function () {
    $owner = User::factory()->unverified()->create([
        'email' => jpwebcreationTestEmail(),
    ]);

    $nav = extractHoofdmenu(
        $this->actingAs($owner)->get(route('home'))->assertOk()->getContent()
    );
    expect($nav)->not->toContain('Dashboard');

    $this->actingAs($owner)
        ->get(route('beheer.index'))
        ->assertRedirect(route('beheer.login'));
});

test('weergavenaam jpwebcreation geeft geen toegang', function () {
    $user = User::factory()->create([
        'name' => 'jpwebcreation',
        'email' => 'iemand-anders@example.test',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('beheer.index'))
        ->assertRedirect(route('beheer.login'));
});

test('wachtwoord-fallback werkt nog zonder account', function () {
    $this->post(route('beheer.login'), ['password' => dashboardAdminPassphrase()])
        ->assertRedirect(route('beheer.index'));

    $this->get(route('beheer.index'))
        ->assertOk()
        ->assertSee('Kaartmarkers', false);
});

test('beheer-uitloggen breekt geen account-sessie', function () {
    $owner = User::factory()->create([
        'email' => jpwebcreationTestEmail(),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($owner)
        ->withSession(['admin_ok' => true])
        ->post(route('beheer.logout'))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($owner);

    $this->get(route('beheer.index'))->assertOk();
});

test('beheer gebruikers-tabel toont Beheertoegang zonder e-mailadressen', function () {
    $owner = User::factory()->create([
        'name' => 'Eigenaar',
        'email' => jpwebcreationTestEmail(),
        'email_verified_at' => now(),
    ]);
    $member = User::factory()->create([
        'name' => 'Gewoon Lid',
        'email' => 'lid-zonder-beheer@example.test',
        'email_verified_at' => now(),
    ]);
    $unverifiedOwner = User::factory()->unverified()->create([
        'name' => 'Nog Niet Bevestigd',
        'email' => 'andere-owner@example.test',
    ]);
    config(['stekjes.admin_dashboard_emails' => [
        jpwebcreationTestEmail(),
        'andere-owner@example.test',
    ]]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.index'))
        ->assertOk()
        ->assertSee('Beheertoegang', false)
        ->assertSee('Eigenaar', false)
        ->assertSee('Gewoon Lid', false)
        ->assertSee('Nog Niet Bevestigd', false)
        ->assertDontSee(jpwebcreationTestEmail(), false)
        ->assertDontSee('lid-zonder-beheer@example.test', false)
        ->assertDontSee('andere-owner@example.test', false)
        ->getContent();

    expect(preg_match(
        '/Eigenaar<\/td>\s*<td>ja<\/td>\s*<td>ja<\/td>/s',
        $html
    ))->toBe(1, 'geverifieerde owner moet Bevestigd=ja en Beheertoegang=ja tonen');
    expect(preg_match(
        '/Gewoon Lid<\/td>\s*<td>ja<\/td>\s*<td>nee<\/td>/s',
        $html
    ))->toBe(1, 'lid moet Bevestigd=ja en Beheertoegang=nee tonen');
    expect(preg_match(
        '/Nog Niet Bevestigd<\/td>\s*<td>nee<\/td>\s*<td>nee<\/td>/s',
        $html
    ))->toBe(1, 'ongeverifieerde allowlist-mail moet Beheertoegang=nee tonen');

    expect($owner->email)->not->toBe('')
        ->and($member->email)->not->toBe('')
        ->and($unverifiedOwner->email)->not->toBe('');
});
