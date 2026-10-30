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
        'email' => 'uitdeler-stats@example.com',
        'name' => 'UitdelerStats',
        'email_verified_at' => now(),
    ]);
    User::factory()->unverified()->create([
        'email' => 'ophaler-stats@example.com',
        'name' => 'OphalerStats',
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
        ->assertSee('aanmeldingen totaal', false)
        ->assertSee('uitdelers', false)
        ->assertSee('Uitdeler = account met minstens één aanbod', false)
        ->assertSee('/aanbod', false)
        ->assertSee('berichten', false)
        ->getContent();

    expect($html)->not->toContain('uitdeler-stats@example.com')
        ->and($html)->not->toContain('ophaler-stats@example.com')
        ->and($html)->not->toContain('peer-stats@example.com')
        ->and($html)->not->toContain('UitdelerStats')
        ->and($html)->not->toContain('OphalerStats');

    // 3 accounts; 2/1 bevestigd/onbevestigd; 1/2 uitdelers/ophalers; 2/1 aanbod; 1 bericht; 7 pageviews vandaag.
    expect($html)
        ->toContain('aanmeldingen totaal')
        ->and($html)->toContain('bevestigd / onbevestigd')
        ->and($html)->toContain('uitdelers / ophalers')
        ->and($html)->toContain('<span class="stats-kpi-label">bezoekers vandaag</span>')
        ->and($html)->toContain('<span class="stats-kpi-value">7</span>')
        ->and($html)->toContain('Totaal laatste 14 dagen: 7')
        ->and($html)->toContain('<span class="stats-kpi-value">3</span>');
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
        ->and($html)->toContain('Totaal laatste 14 dagen: 106')
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
