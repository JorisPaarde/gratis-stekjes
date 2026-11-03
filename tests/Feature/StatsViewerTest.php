<?php

declare(strict_types=1);

use App\Models\Feedback;
use App\Models\PageView;
use App\Models\User;
use App\Services\MilestoneService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

function makeStatsViewer(array $extra = []): User
{
    $user = User::factory()->create(array_merge([
        'email' => 'plantjes-stats@example.test',
        'email_verified_at' => now(),
        'name' => 'Plantjes (stats)',
    ], $extra));
    $user->forceFill([
        'is_stats_viewer' => true,
        'is_admin' => false,
    ])->save();

    return $user->fresh();
}

test('stats_viewer krijgt 200 op statistieken en geen beheer-tabs', function () {
    $viewer = makeStatsViewer();

    $html = $this->actingAs($viewer)
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('Statistieken', false)
        ->assertDontSee('>Overzicht<', false)
        ->assertDontSee('>Gebruikers<', false)
        ->getContent();

    expect($html)->not->toContain(route('beheer.feedback'))
        ->and($html)->not->toContain('plantjes-stats@example.test');
});

test('stats_viewer wordt geweerd van overzicht, gebruikers, feedback en stijlgids', function () {
    $viewer = makeStatsViewer(['email' => 'stats-deny@example.test']);

    foreach ([
        route('beheer.index'),
        route('beheer.gebruikers'),
        route('beheer.feedback'),
        route('stijlgids'),
    ] as $url) {
        $this->actingAs($viewer)
            ->get($url)
            ->assertRedirect(route('beheer.login'));
    }
});

test('stats_viewer mag geen beheerder-toggle of feedback-delete', function () {
    $viewer = makeStatsViewer(['email' => 'stats-post@example.test']);
    $lid = User::factory()->create(['email' => 'gewoon-lid@example.test']);
    $feedback = Feedback::query()->create([
        'user_id' => null,
        'page_path' => '/over',
        'body' => 'Hallo feedback zonder PII-test',
    ]);

    $this->actingAs($viewer)
        ->post(route('beheer.gebruikers.beheerder', $lid))
        ->assertRedirect(route('beheer.login'));

    $this->actingAs($viewer)
        ->delete(route('beheer.feedback.destroy', $feedback))
        ->assertRedirect(route('beheer.login'));

    expect((bool) $lid->fresh()->is_admin)->toBeFalse()
        ->and(Feedback::query()->whereKey($feedback->id)->exists())->toBeTrue();
});

test('stats_viewer telt niet mee in ledentelling en lidnummer', function () {
    User::factory()->count(2)->create();
    $viewer = makeStatsViewer(['email' => 'stats-count@example.test']);

    expect(User::query()->count())->toBe(3)
        ->and(User::query()->members()->count())->toBe(2)
        ->and(app(MilestoneService::class)->lidnummer($viewer))->toBeNull();

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<span class="stats-kpi-value">2</span>');
});

test('stats_viewer pageviews worden niet geregistreerd', function () {
    $viewer = makeStatsViewer(['email' => 'stats-views@example.test']);
    $before = (int) (PageView::query()->where('date', now()->toDateString())->where('path', '/over')->value('count') ?? 0);

    $this->actingAs($viewer)
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsViewerBrowser/1.0)'])
        ->get('/over')
        ->assertOk();

    $after = (int) (PageView::query()->where('date', now()->toDateString())->where('path', '/over')->value('count') ?? 0);
    expect($after)->toBe($before);
});

test('stats_viewer ziet Statistieken in menu, geen Dashboard of Bied aan', function () {
    $viewer = makeStatsViewer(['email' => 'stats-nav@example.test']);
    $html = $this->actingAs($viewer)->get(route('home'))->assertOk()->getContent();

    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $html, $nav))->toBe(1);
    expect($nav[1])->toContain('Statistieken')
        ->and($nav[1])->toContain(route('beheer.statistieken'))
        ->and($nav[1])->not->toContain('Dashboard')
        ->and($nav[1])->not->toContain(route('beheer.index'))
        ->and($nav[1])->not->toContain('Bied iets aan')
        ->and($html)->not->toContain('header-cta" href="'.route('aanbieden').'"');
});

test('stats_viewer kan geen aanbod plaatsen', function () {
    $viewer = makeStatsViewer([
        'email' => 'stats-aanbod@example.test',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);

    $this->actingAs($viewer)
        ->get(route('aanbieden'))
        ->assertRedirect(route('account.index'));

    $this->actingAs($viewer)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Mag niet',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertForbidden();
});

test('gp:stats-lezer is idempotent en zet geen admin', function () {
    $email = 'plantjes-cmd@example.test';
    putenv('GP_PLANTJES_PASSWORD=TestOnly-Plantjes-Pass-9');
    $_ENV['GP_PLANTJES_PASSWORD'] = 'TestOnly-Plantjes-Pass-9';

    try {
        expect(Artisan::call('gp:stats-lezer', ['email' => $email]))->toBe(0);
        $first = User::query()->where('email', $email)->first();
        expect($first)->not->toBeNull()
            ->and((bool) $first->is_stats_viewer)->toBeTrue()
            ->and((bool) $first->is_admin)->toBeFalse()
            ->and($first->email_verified_at)->not->toBeNull()
            ->and($first->name)->toBe('Plantjes (stats)')
            ->and(Hash::check('TestOnly-Plantjes-Pass-9', $first->password))->toBeTrue();

        $id = $first->id;
        expect(Artisan::call('gp:stats-lezer', ['email' => $email]))->toBe(0);
        $again = User::query()->where('email', $email)->first();
        expect($again->id)->toBe($id)
            ->and(User::query()->where('email', $email)->count())->toBe(1)
            ->and((bool) $again->is_admin)->toBeFalse()
            ->and((bool) $again->is_stats_viewer)->toBeTrue();
    } finally {
        putenv('GP_PLANTJES_PASSWORD');
        unset($_ENV['GP_PLANTJES_PASSWORD']);
    }
});

test('is_stats_viewer is niet mass-assignable', function () {
    expect((new User)->isFillable('is_stats_viewer'))->toBeFalse();
});
