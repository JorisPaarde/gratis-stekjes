<?php

declare(strict_types=1);

/** @return list<string> */
function audienceLandingPaths(): array
{
    return ['/volkstuinen', '/help-mee', '/natuur', '/verbinding'];
}

test('doelgroep-landingspagina’s geven 200 met SEO-title, H1, CTA en schema', function () {
    $cases = [
        'volkstuinen' => [
            'title' => 'Volkstuinvereniging: planten en oogst gratis delen',
            'heading' => 'Stekjes delen binnen je eigen volkstuin',
            'cta' => 'Zet het eerste stekje van jouw tuin online',
            'see' => ['QR', 'clubhuis', 'voor-organisaties/aanmelden'],
            'dont' => ['Lucy', 'partnerschap met'],
        ],
        'help-mee' => [
            'title' => 'Help mee: vrijwilligerswerk voor planten en natuur',
            'heading' => 'Breng jij mensen graag bij elkaar?',
            'cta' => 'Meld je als verbinder: 2 uur per maand, stoppen kan altijd',
            'see' => ['contact', 'feedback'],
            'dont' => ['help-mee.store', 'help_mee_interests'],
        ],
        'natuur' => [
            'title' => 'Inheemse planten gratis delen: bijen- en vlinderplanten',
            'heading' => 'Meer groen, zonder één plant te kopen',
            'cta' => 'Bied je snoeiafval aan als stek',
            'see' => ['inheemse planten', 'bijenplanten'],
            'dont' => [],
        ],
        'verbinding' => [
            'title' => 'Plantenbieb, stekjesmarkt en buurttuin: samen delen',
            'heading' => 'Leer je buren kennen via een stekje',
            'cta' => 'Kijk wat je buren weggeven',
            'see' => ['praatje', 'aanbod'],
            'dont' => [],
        ],
    ];

    foreach ($cases as $name => $case) {
        $html = $this->get(route($name))
            ->assertOk()
            ->assertSee('<h1 class="page-title">'.$case['heading'].'</h1>', false)
            ->assertSee($case['title'], false)
            ->assertSee($case['cta'], false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('property="og:description"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"WebPage"', false)
            ->getContent();

        foreach ($case['see'] as $needle) {
            expect($html)->toContain($needle);
        }
        foreach ($case['dont'] as $needle) {
            expect($html)->not->toContain($needle);
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        expect(preg_match('/\b(we|wij|ons|onze)\b/iu', $text))->toBe(
            0,
            "Pagina {$name} bevat we/wij/ons/onze."
        );
        expect($html)->not->toContain('Lucy')
            ->and($html)->not->toContain('partnerschap met');
    }
});

test('doelgroeppagina’s staan in sitemap maar niet in menu of footer', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    $home = $this->get('/')->assertOk()->getContent();

    foreach (audienceLandingPaths() as $path) {
        expect($xml)->toContain($path);
    }

    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $home, $nav))->toBe(1);
    expect(preg_match('/<p class="footer-links"[^>]*>(.*?)<\/p>/s', $home, $footer))->toBe(1);

    foreach (['volkstuinen', 'help-mee', 'natuur', 'verbinding'] as $slug) {
        expect($nav[1])->not->toContain($slug);
        expect($footer[1])->not->toContain($slug);
    }

    // Homepage-CTA mag wél naar Help mee (buiten nav/footer); geen “binnenkort”-badge meer in dat paneel.
    expect(preg_match('/<div class="cta-panel"[^>]*>(.*?)<\/div>\s*<\/section>/s', $home, $cta))->toBe(1);
    expect($cta[1])->toMatch('/href="[^"]*help-mee"/')
        ->and($cta[1])->not->toContain('badge-soon')
        ->and($cta[1])->not->toContain('binnenkort');
    expect($home)->not->toMatch('/href="[^"]*\/volkstuinen"/')
        ->and($home)->not->toMatch('/href="[^"]*\/natuur"/')
        ->and($home)->not->toMatch('/href="[^"]*\/verbinding"/');
});

test('help-mee heeft geen eigen post-endpoint en verwijst naar contact', function () {
    $this->post('/help-mee', [])->assertStatus(405);

    $this->get(route('help-mee'))
        ->assertOk()
        ->assertSee('Meld je als verbinder: 2 uur per maand, stoppen kan altijd', false)
        ->assertSee(route('contact'), false)
        ->assertSee('feedback', false);
});
