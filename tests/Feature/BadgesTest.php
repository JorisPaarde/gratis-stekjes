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

    expect($badges)->toHaveCount(10);
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
        ->assertRedirect(route('beheer.index'));
    expect(app(MilestoneService::class)->pionierUserId())->toBe($lid->id);

    $ander = badgeUser('ander@example.com', 'Ander');
    $this->actingAs($lid)->post(route('beheer.users.pionier', $ander))->assertRedirect(route('beheer.login'));
    expect(app(MilestoneService::class)->pionierUserId())->toBe($lid->id);
});
