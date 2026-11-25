<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MilestoneSent;
use App\Models\User;
use App\Services\MilestoneService;

function badgeUser(string $email, string $name = 'Sam'): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => $name,
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
}

function badgeAanbod(User $u, string $type = 'uitdelen', ?string $at = null): Aanbod
{
    $a = Aanbod::query()->create([
        'user_id' => $u->id,
        'type' => $type,
        'tekst' => 'Stekje',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
    if ($at !== null) {
        $a->forceFill(['created_at' => $at])->save();
    }

    return $a;
}

test('alle badges hebben een plaatje en tellen mee', function () {
    $u = badgeUser('b1@example.com');
    $badges = app(MilestoneService::class)->badgesFor($u);

    expect($badges)->toHaveCount(11); // incl. één oprichtersbadge (hoogste tier)
    foreach ($badges as $b) {
        expect(is_file(public_path(ltrim($b['img'], '/'))))->toBeTrue();
    }
    expect(collect($badges)->firstWhere('key', 'geworteld')['earned'])->toBeTrue();
    expect(collect($badges)->firstWhere('key', 'gulle_gever')['hint'])->toBe('Nog 5 keer weggeven te gaan.');
});

test('badges worden verdiend door delen, praten, overdracht', function () {
    $a = badgeUser('b2@example.com', 'Ada');
    $b = badgeUser('b3@example.com', 'Ben');
    foreach (range(1, 5) as $i) {
        badgeAanbod($a);
    }
    $conv = Conversation::query()->create(['user_a_id' => min($a->id, $b->id), 'user_b_id' => max($a->id, $b->id), 'overdracht_at' => now()]);
    Message::query()->create(['conversation_id' => $conv->id, 'sender_id' => $a->id, 'body' => 'Hoi', 'created_at' => now()]);

    $badges = collect(app(MilestoneService::class)->badgesFor($a))->keyBy('key');
    expect($badges['eerste_stekje']['earned'])->toBeTrue()
        ->and($badges['gulle_gever']['earned'])->toBeTrue()
        ->and($badges['stekjesheld']['earned'])->toBeFalse()
        ->and($badges['eerste_praatje']['earned'])->toBeTrue()
        ->and($badges['eerste_overdracht']['earned'])->toBeTrue()
        ->and($badges['verbinder']['hint'])->toBe('Nog 2 overdrachten te gaan.');
});

test('pionier: eerste deler krijgt hem, er is er maar één, beheerders tellen niet', function () {
    config(['stekjes.admin_dashboard_emails' => ['admin@example.com']]);
    $admin = badgeUser('admin@example.com', 'Joris');
    $eerste = badgeUser('eerste@example.com', 'Eerste');
    $later = badgeUser('later@example.com', 'Later');
    badgeAanbod($admin, 'uitdelen', '2026-09-01 10:00:00');
    badgeAanbod($eerste, 'ophalen', '2026-09-02 10:00:00');
    badgeAanbod($eerste, 'uitdelen', '2026-09-03 10:00:00');
    badgeAanbod($later, 'uitdelen', '2026-09-04 10:00:00');

    $service = app(MilestoneService::class);
    expect($service->eersteDeler()?->id)->toBe($eerste->id);

    $service->setPionier($eerste);
    $service->setPionier($eerste);
    expect(MilestoneSent::query()->where('milestone', 'pionier')->count())->toBe(1);

    $service->setPionier($later);
    expect(MilestoneSent::query()->where('milestone', 'pionier')->count())->toBe(1)
        ->and($service->pionierUserId())->toBe($later->id);

    expect(collect($service->badgesFor($later))->firstWhere('key', 'pionier'))->not->toBeNull();
    expect(collect($service->badgesFor($eerste))->firstWhere('key', 'pionier'))->toBeNull();
});

test('account toont badges met plaatjes en de pionier', function () {
    $u = badgeUser('b4@example.com', 'Piet');
    badgeAanbod($u);
    app(MilestoneService::class)->setPionier($u);

    $this->actingAs($u)->get(route('account.index'))
        ->assertOk()
        ->assertSee('Badges', false)
        ->assertSee('/assets/badges/eerste_stekje.svg', false)
        ->assertSee('/assets/badges/pionier.svg', false)
        ->assertSee('er is er maar één', false)
        ->assertSee('badge-item is-verdiend', false);
});

test('beheer kan de pionier verplaatsen', function () {
    config(['stekjes.admin_dashboard_emails' => ['admin2@example.com']]);
    $admin = badgeUser('admin2@example.com', 'Joris');
    $lid = badgeUser('lid@example.com', 'Lid');

    $this->actingAs($admin)->post(route('beheer.users.pionier', $lid))
        ->assertRedirect(route('beheer.gebruikers.show', $lid));
    expect(app(MilestoneService::class)->pionierUserId())->toBe($lid->id);

    $ander = badgeUser('ander@example.com', 'Ander');
    $this->actingAs($lid)->post(route('beheer.users.pionier', $ander))->assertRedirect(route('beheer.login'));
    expect(app(MilestoneService::class)->pionierUserId())->toBe($lid->id);
});

test('oprichtersbadges: hoogste tier op volgorde van aanmelden, beheerders tellen niet mee voor anderen', function () {
    config(['stekjes.admin_dashboard_emails' => ['admin-opr@example.com']]);
    $admin = badgeUser('admin-opr@example.com', 'Beheer');
    $leden = collect(range(1, 12))->map(fn ($i) => badgeUser('opr'.$i.'@example.com', 'Lid'.$i));
    $service = app(MilestoneService::class);

    $eerste = collect($service->badgesFor($leden[0]))->keyBy('key');
    expect($service->lidnummer($leden[0]))->toBe(1)
        ->and($eerste->has('eerste_10'))->toBeTrue()
        ->and($eerste->has('eerste_50'))->toBeFalse()
        ->and($eerste['eerste_10']['hint'])->toContain('lid nummer 1');

    $elfde = collect($service->badgesFor($leden[10]))->keyBy('key');
    expect($service->lidnummer($leden[10]))->toBe(11)
        ->and($elfde->has('eerste_10'))->toBeFalse()
        ->and($elfde->has('eerste_50'))->toBeTrue();

    // Vertrekt een eerder lid, dan schuift de rest op (nooit achteruit).
    $leden[0]->delete();
    expect($service->lidnummer($leden[10]))->toBe(10);

    // Beheerder krijgt zelf wél een lidnummer (zichzelf meegeteld) en de badge.
    // Andere leden schuiven daardoor niet op: lid 2 blijft 2 (was 1 na delete + zelf).
    $admin->refresh();
    $adminBadges = collect($service->badgesFor($admin))->keyBy('key');
    expect($service->lidnummer($admin))->toBe(1)
        ->and($adminBadges->has('eerste_10'))->toBeTrue()
        ->and($adminBadges['eerste_10']['hint'])->toContain('lid nummer 1')
        ->and($service->lidnummer($leden[1]))->toBe(1);

    foreach (['eerste_10', 'eerste_50', 'eerste_100', 'eerste_200'] as $k) {
        expect(is_file(public_path('assets/badges/'.$k.'.svg')))->toBeTrue();
    }
});

test('oprichtersbadge eerste 200 voor lid 101 tot 200, daarna geen tier', function () {
    // 200 leden: 1–10 eerste_10, 11–50 eerste_50, 51–100 eerste_100, 101–200 eerste_200.
    User::factory()->count(201)->create();
    $leden = User::query()->members()->orderBy('id')->get();
    $service = app(MilestoneService::class);

    expect($leden)->toHaveCount(201)
        ->and($service->lidnummer($leden[0]))->toBe(1)
        ->and($service->lidnummer($leden[9]))->toBe(10)
        ->and($service->lidnummer($leden[10]))->toBe(11)
        ->and($service->lidnummer($leden[49]))->toBe(50)
        ->and($service->lidnummer($leden[50]))->toBe(51)
        ->and($service->lidnummer($leden[99]))->toBe(100)
        ->and($service->lidnummer($leden[100]))->toBe(101)
        ->and($service->lidnummer($leden[199]))->toBe(200)
        ->and($service->lidnummer($leden[200]))->toBe(201);

    $keys = fn (User $u) => collect($service->badgesFor($u))->pluck('key');

    expect($keys($leden[0])->intersect(['eerste_10', 'eerste_50', 'eerste_100', 'eerste_200'])->all())->toBe(['eerste_10'])
        ->and($keys($leden[10])->intersect(['eerste_10', 'eerste_50', 'eerste_100', 'eerste_200'])->all())->toBe(['eerste_50'])
        ->and($keys($leden[50])->intersect(['eerste_10', 'eerste_50', 'eerste_100', 'eerste_200'])->all())->toBe(['eerste_100'])
        ->and($keys($leden[100])->intersect(['eerste_10', 'eerste_50', 'eerste_100', 'eerste_200'])->all())->toBe(['eerste_200'])
        ->and($keys($leden[199])->intersect(['eerste_10', 'eerste_50', 'eerste_100', 'eerste_200'])->all())->toBe(['eerste_200'])
        ->and($keys($leden[200])->intersect(['eerste_10', 'eerste_50', 'eerste_100', 'eerste_200'])->all())->toBe([]);

    $tweehonderd = collect($service->badgesFor($leden[100]))->keyBy('key');
    expect($tweehonderd['eerste_200']['label'])->toBe('Een van de eerste 200')
        ->and($tweehonderd['eerste_200']['earned'])->toBeTrue()
        ->and($tweehonderd['eerste_200']['hint'])->toContain('lid nummer 101')
        ->and($tweehonderd['eerste_200']['img'])->toBe('/assets/badges/eerste_200.svg');
});

test('elke badge heeft een doel: actie of uitleg, geen ontbrekende sleutel', function () {
    $service = app(MilestoneService::class);
    $keys = MilestoneService::alleBadgeKeys();

    expect($keys)->toContain('geworteld', 'profiel_compleet', 'eerste_stekje', 'eerste_praatje', 'eerste_overdracht', 'gulle_gever', 'stekjesheld', 'verbinder', 'verteller', 'vaste_plant', 'pionier', 'eerste_10', 'eerste_50', 'eerste_100', 'eerste_200')
        ->and($keys)->toHaveCount(15);

    foreach ($keys as $key) {
        $doel = $service->doelVoor($key);
        expect($doel['type'])->toBeIn(['actie', 'uitleg'])
            ->and($doel['href'])->not->toBeEmpty()
            ->and($doel['label'])->not->toBeEmpty();
        if ($doel['type'] === 'uitleg') {
            expect($doel['href'])->toContain('#uitleg-');
        }
    }

    expect(fn () => $service->doelVoor('onbekende_badge'))
        ->toThrow(InvalidArgumentException::class);
});

test('badgesFor vult doel voor elke getoonde badge, ook oprichters en pionier', function () {
    $u = badgeUser('doel@example.com', 'Ada');
    $service = app(MilestoneService::class);
    $service->setPionier($u);
    $badges = collect($service->badgesFor($u));

    expect($badges->pluck('key')->diff(MilestoneService::alleBadgeKeys())->all())->toBeEmpty();
    foreach ($badges as $badge) {
        expect($badge)->toHaveKey('doel')
            ->and($badge['doel']['href'])->toBe($service->doelVoor($badge['key'])['href'])
            ->and($badge['doel']['label'])->not->toBeEmpty();
    }

    expect($badges->firstWhere('key', 'pionier')['doel']['href'])->toEndWith('#uitleg-pionier')
        ->and($badges->firstWhere('key', 'eerste_10')['doel']['href'])->toEndWith('#uitleg-oprichters')
        ->and($badges->firstWhere('key', 'geworteld')['doel']['href'])->toEndWith('#profiel-postcode')
        ->and($badges->firstWhere('key', 'profiel_compleet')['doel']['href'])->toEndWith('#profiel-adres')
        ->and($badges->firstWhere('key', 'eerste_stekje')['doel']['href'])->toBe(route('aanbieden'))
        ->and($badges->firstWhere('key', 'eerste_praatje')['doel']['href'])->toBe(route('aanbod'))
        ->and($badges->firstWhere('key', 'eerste_overdracht')['doel']['href'])->toBe(route('account.inbox'))
        ->and($badges->firstWhere('key', 'verteller')['doel']['href'])->toBe(route('account.ervaringen.create'))
        ->and($badges->firstWhere('key', 'vaste_plant')['doel']['href'])->toEndWith('#uitleg-vaste-plant');
});

test('account toont klikbare badges naar het juiste doel en uitlegankers', function () {
    $u = badgeUser('klik@example.com', 'Piet');
    app(MilestoneService::class)->setPionier($u);

    $this->actingAs($u)->get(route('account.index'))
        ->assertOk()
        ->assertSee('class="badge-item is-verdiend"', false)
        ->assertSee('href="'.route('aanbieden').'"', false)
        ->assertSee('href="'.route('aanbod').'"', false)
        ->assertSee('href="'.route('account.inbox').'"', false)
        ->assertSee('href="'.route('account.ervaringen.create').'"', false)
        ->assertSee('href="'.route('account.profiel').'#profiel-postcode"', false)
        ->assertSee('href="'.route('account.profiel').'#profiel-adres"', false)
        ->assertSee('href="'.route('account.index').'#uitleg-oprichters"', false)
        ->assertSee('href="'.route('account.index').'#uitleg-pionier"', false)
        ->assertSee('href="'.route('account.index').'#uitleg-vaste-plant"', false)
        ->assertSee('id="uitleg-oprichters"', false)
        ->assertSee('id="uitleg-pionier"', false)
        ->assertSee('id="uitleg-vaste-plant"', false)
        ->assertSee('Je kunt ze niet achteraf verdienen', false);
});

test('profielpagina heeft ankers voor postcode en adres', function () {
    $u = badgeUser('anker@example.com', 'Piet');

    $this->actingAs($u)->get(route('account.profiel'))
        ->assertOk()
        ->assertSee('id="profiel-voornaam"', false)
        ->assertSee('id="profiel-postcode"', false)
        ->assertSee('id="profiel-adres"', false)
        ->assertSee('id="profiel-straat"', false)
        ->assertSee('focusHash', false);
});
