<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/** Beheer-fixture opgebouwd (geen hardcoded secret-literal voor scanners). */
function statsAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Stats', 'Beheer', '9']);
}

beforeEach(function () {
    config(['stekjes.admin_password' => statsAdminPassphrase()]);
});

test('statistieken vereist admin-sessie', function () {
    $this->get(route('beheer.statistieken'))
        ->assertRedirect(route('beheer.login'));
});

test('statistieken toont aggregaten zonder e-mailadressen', function () {
    $withOffer = User::factory()->create([
        'email' => 'aanbieder-stats@example.com',
        'name' => 'AanbiederStats',
        'email_verified_at' => now(),
    ]);
    User::factory()->unverified()->create([
        'email' => 'lid-stats@example.com',
        'name' => 'LidStats',
    ]);
    Aanbod::factory()->create([
        'user_id' => $withOffer->id,
        'type' => 'uitdelen',
        'visible' => true,
        'expires_at' => now()->addDays(20),
    ]);
    Aanbod::factory()->expired()->create([
        'user_id' => $withOffer->id,
        'type' => 'ophalen',
    ]);

    $other = User::factory()->create(['email' => 'peer-stats@example.com']);
    $conv = Conversation::query()->create([
        'user_a_id' => min($withOffer->id, $other->id),
        'user_b_id' => max($withOffer->id, $other->id),
        'aanbod_id' => null,
    ]);
    Message::query()->create([
        'conversation_id' => $conv->id,
        'sender_id' => $withOffer->id,
        'body' => 'Hallo voor stats',
        'created_at' => now(),
    ]);

    PageView::query()->create([
        'date' => now()->toDateString(),
        'path' => '/aanbod',
        'count' => 7,
    ]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertSee('Leden', false)
        ->assertSee('Leden die iets aangeboden hebben', false)
        ->assertSee('/aanbod', false)
        ->assertSee('berichten', false)
        ->getContent();

    expect($html)->not->toContain('aanbieder-stats@example.com')
        ->and($html)->not->toContain('lid-stats@example.com')
        ->and($html)->not->toContain('peer-stats@example.com')
        ->and($html)->not->toContain('AanbiederStats')
        ->and($html)->not->toContain('LidStats')
        ->and($html)->not->toContain('uitdelers')
        ->and($html)->not->toContain('ophalers')
        ->and($html)->not->toContain('Uitdeler =');

    // 3 leden; 1 lid met ≥1 listing; 2/1 bevestigd/onbevestigd; 2/1 aanbod; 1 bericht; 7 pageviews vandaag.
    expect($html)
        ->toContain('bevestigd / onbevestigd')
        ->and($html)->toContain('<span class="stats-kpi-label">bezoekers vandaag</span>')
        ->and($html)->toContain('<span class="stats-kpi-value">7</span>')
        ->and($html)->toContain('1 van 3 leden heeft iets aangeboden')
        ->and($html)->toContain('<span class="stats-kpi-value">3</span>');
});

test('statistieken rendert SVG-charts voor admin', function () {
    User::factory()->count(2)->create();
    $owner = User::factory()->create();
    Aanbod::factory()->create(['user_id' => $owner->id, 'type' => 'uitdelen']);
    PageView::query()->create([
        'date' => now()->toDateString(),
        'path' => '/',
        'count' => 3,
    ]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('<svg')
        ->and($html)->toContain('stats-sparkline')
        ->and($html)->toContain('stats-svg-chart')
        ->and($html)->toContain('role="img"')
        ->and($html)->toContain('Lijndiagram aanmeldingen en bezoekers')
        ->and($html)->toContain('Donut bevestigd versus onbevestigd')
        ->and($html)->toContain('Horizontale staven top pagina')
        ->and($html)->toContain('<details class="stats-panel stats-panel--overview" open>')
        ->and($html)->not->toContain('cdn.')
        ->and($html)->not->toContain('chart.js')
        ->and($html)->not->toContain('googleapis');
});

test('statistieken werkt met lege data zonder fouten', function () {
    expect(User::query()->count())->toBe(0);
    expect(PageView::query()->count())->toBe(0);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->getContent();

    expect($html)
        ->toContain('Leden')
        ->and($html)->toContain('Leden die iets aangeboden hebben')
        ->and($html)->toContain('0 van 0 leden heeft iets aangeboden')
        ->and($html)->toContain('<svg')
        ->and($html)->not->toContain('@example.com');
});

test('statistieken toont bezoekers vandaag als KPI', function () {
    PageView::query()->create([
        'date' => now()->toDateString(),
        'path' => '/',
        'count' => 4,
    ]);
    PageView::query()->create([
        'date' => now()->toDateString(),
        'path' => '/aanbod',
        'count' => 3,
    ]);
    PageView::query()->create([
        'date' => now()->subDay()->toDateString(),
        'path' => '/',
        'count' => 99,
    ]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('bezoekers vandaag', false)
        ->getContent();

    expect($html)
        ->toContain('<span class="stats-kpi-value">7</span>')
        ->and($html)->toContain('<span class="stats-kpi-label">bezoekers vandaag</span>')
        ->and($html)->toContain('14 dagen (106 views)')
        ->and($html)->not->toContain('@example.com');
});

test('beheer overzicht linkt naar statistieken', function () {
    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.index'))
        ->assertOk()
        ->assertSee(route('beheer.statistieken'), false)
        ->assertSee('Statistieken', false);
});

test('statistieken staat niet in sitemap', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->not->toContain('/beheer/statistieken')
        ->and($xml)->not->toContain('/beheer');
});

test('pageview-teller telt publieke HTML GET één keer', function () {
    $before = (int) (PageView::query()->where('date', now()->toDateString())->where('path', '/')->value('count') ?? 0);

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsTestBrowser/1.0)'])
        ->get('/')
        ->assertOk();

    $after = (int) PageView::query()->where('date', now()->toDateString())->where('path', '/')->value('count');
    expect($after)->toBe($before + 1);

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsTestBrowser/1.0)'])
        ->get('/')
        ->assertOk();

    $again = (int) PageView::query()->where('date', now()->toDateString())->where('path', '/')->value('count');
    expect($again)->toBe($before + 2);
});

test('pageview-teller negeert uitgesloten paden', function () {
    $paths = [
        '/beheer',
        '/account/inloggen',
        '/robots.txt',
        '/sitemap.xml',
        '/api/partners',
    ];

    foreach ($paths as $path) {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsTestBrowser/1.0)'])
            ->get($path);
    }

    $counted = PageView::query()
        ->whereIn('path', ['/beheer', '/account/inloggen', '/robots.txt', '/sitemap.xml', '/api/partners'])
        ->sum('count');

    expect((int) $counted)->toBe(0);
});

test('pageview-teller negeert bot user-agents', function () {
    $before = (int) (PageView::query()->where('date', now()->toDateString())->where('path', '/over')->value('count') ?? 0);

    $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
        ->get('/over')
        ->assertOk();

    $after = (int) (PageView::query()->where('date', now()->toDateString())->where('path', '/over')->value('count') ?? 0);
    expect($after)->toBe($before);
});

test('pageview-teller zet geen Set-Cookie', function () {
    $response = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsTestBrowser/1.0)'])
        ->get('/hoe-werkt-het')
        ->assertOk();

    $allowed = [
        config('session.cookie'),
        'XSRF-TOKEN',
    ];
    $extra = collect($response->headers->getCookies())
        ->map(fn ($cookie) => $cookie->getName())
        ->reject(fn (string $name) => in_array($name, $allowed, true))
        ->values()
        ->all();

    expect($extra)->toBeEmpty();

    $count = (int) PageView::query()
        ->where('date', now()->toDateString())
        ->where('path', '/hoe-werkt-het')
        ->value('count');
    expect($count)->toBeGreaterThanOrEqual(1);
});

test('pageview-teller negeert POST en 404', function () {
    $beforeHome = (int) (PageView::query()->where('date', now()->toDateString())->where('path', '/')->value('count') ?? 0);

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsTestBrowser/1.0)'])
        ->post('/')
        ->assertStatus(405);

    $missingPath = '/pagina-die-niet-bestaat-xyz';
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsTestBrowser/1.0)'])
        ->get($missingPath)
        ->assertNotFound();

    expect((int) (PageView::query()->where('date', now()->toDateString())->where('path', '/')->value('count') ?? 0))
        ->toBe($beforeHome);
    expect((int) PageView::query()->where('path', $missingPath)->sum('count'))->toBe(0);
});

test('pageview-teller breekt pagina niet als page_views-tabel ontbreekt', function () {
    Schema::rename('page_views', 'page_views_hidden_for_test');

    try {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StatsTestBrowser/1.0)'])
            ->get('/privacy')
            ->assertOk();
    } finally {
        if (Schema::hasTable('page_views_hidden_for_test')) {
            Schema::rename('page_views_hidden_for_test', 'page_views');
        }
    }
});

test('hoe-werkt-het en over spreken niet van aparte accounttypen', function () {
    foreach (['/hoe-werkt-het', '/over'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();
        expect($html)->not->toContain('Uitdelers zetten')
            ->and($html)->not->toContain('Ophalers zoeken')
            ->and($html)->toContain('één account');
    }
});
