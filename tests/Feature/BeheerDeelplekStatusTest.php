<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Models\DeelplekTombstone;
use App\Models\User;

function statusTestAdmin(): User
{
    config(['stekjes.admin_dashboard_emails' => ['status-admin@example.com']]);

    return User::factory()->create([
        'name' => 'Status Admin',
        'email' => 'status-admin@example.com',
        'email_verified_at' => now(),
    ]);
}

function statusTestPlek(string $slug, string $naam, bool $visible, string $status, string $source = Deelplek::SOURCE_AANMELDING): Deelplek
{
    return Deelplek::query()->create([
        'slug' => $slug,
        'naam' => $naam,
        'type' => 'plantenbieb',
        'plaats' => 'Testdorp',
        'provincie' => 'Utrecht',
        'country' => 'NL',
        'adres' => '1234 AB Testdorp',
        'lat' => 52.1,
        'lng' => 5.1,
        'bron_url' => 'https://example.test/plek',
        'geraadpleegd' => '2026-11-01',
        'visible' => $visible,
        'source' => $source,
        'status' => $status,
    ])->fresh();
}

test('statuslabels: In beoordeling, Afgewezen, Verborgen en Zichtbaar', function () {
    $pending = statusTestPlek('st-pending', 'Pending Plek', false, Deelplek::STATUS_PENDING);
    $rejected = statusTestPlek('st-rejected', 'Afgewezen Plek', false, Deelplek::STATUS_REJECTED);
    $verborgen = statusTestPlek('st-verborgen', 'Verborgen Plek', false, Deelplek::STATUS_APPROVED, Deelplek::SOURCE_SEED);
    $zichtbaar = statusTestPlek('st-zichtbaar', 'Zichtbare Plek', true, Deelplek::STATUS_APPROVED, Deelplek::SOURCE_SEED);

    expect($pending->statusLabel())->toBe('In beoordeling')->and($pending->statusChipClass())->toBe('status--wacht')
        ->and($rejected->statusLabel())->toBe('Afgewezen')->and($rejected->statusChipClass())->toBe('status--fout')
        ->and($verborgen->statusLabel())->toBe('Verborgen')->and($verborgen->statusChipClass())->toBe('status--uit')
        ->and($zichtbaar->statusLabel())->toBe('Zichtbaar')->and($zichtbaar->statusChipClass())->toBe('status--ok');
});

test('beheerlijst toont per rij een eigen statuschip en verwijderknop bij niet-zichtbare plekken', function () {
    $admin = statusTestAdmin();
    $pending = statusTestPlek('st-pending', 'Pending Plek', false, Deelplek::STATUS_PENDING);
    $rejected = statusTestPlek('st-rejected', 'Afgewezen Plek', false, Deelplek::STATUS_REJECTED);
    statusTestPlek('st-verborgen', 'Verborgen Plek', false, Deelplek::STATUS_APPROVED, Deelplek::SOURCE_SEED);

    $html = $this->actingAs($admin)->get(route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen']))
        ->assertOk()
        ->assertSee('status status--wacht">In beoordeling<', false)
        ->assertSee('status status--fout">Afgewezen<', false)
        ->assertSee('status status--uit">Verborgen<', false)
        ->getContent();

    expect($html)->toContain(route('beheer.deelplekken.destroy.confirm', $pending))
        ->and($html)->toContain(route('beheer.deelplekken.destroy.confirm', $rejected));

    $this->actingAs($admin)->get(route('beheer.deelplekken.show', $pending))
        ->assertOk()->assertSee('In beoordeling', false)->assertSee('Verwijderen…', false);
});

test('aanmelding in beoordeling en afgewezen aanmelding zijn verwijderbaar met tombstone', function () {
    $admin = statusTestAdmin();
    $pending = statusTestPlek('st-pending', 'Pending Plek', false, Deelplek::STATUS_PENDING);
    $rejected = statusTestPlek('st-rejected', 'Afgewezen Plek', false, Deelplek::STATUS_REJECTED);

    $this->actingAs($admin)->post(route('beheer.deelplekken.bulk-destroy'), ['ids' => [$pending->id, $rejected->id], 'confirm' => '1'])
        ->assertRedirect(route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen']))
        ->assertSessionHas('status', '2 deelplekken zijn definitief verwijderd.');

    expect(Deelplek::query()->count())->toBe(0)
        ->and(DeelplekTombstone::query()->pluck('slug')->sort()->values()->all())->toBe(['st-pending', 'st-rejected']);
});
