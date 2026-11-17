<?php

declare(strict_types=1);

use Tests\Support\MailRenderer;

/**
 * Regressie: de bevestigingsmail was op iPhone breder dan het scherm door de volle,
 * ondertekende fallback-URL (iOS Mail schaalt dan alles klein).
 */
test('html-mails: geen lange URL als zichtbare linktekst en geen vaste breedte boven 600px', function () {
    foreach (MailRenderer::all() as $key => $mail) {
        $html = $mail['html'];

        preg_match_all('#<a\b[^>]*>(.*?)</a>#s', $html, $links);
        foreach ($links[1] as $label) {
            $visible = trim(strip_tags($label));
            expect(mb_strlen($visible))->toBeLessThanOrEqual(60, "$key: lange linktekst «{$visible}»");
        }

        expect($html)->not->toMatch('#width="(?:5[7-9]\d|[6-9]\d\d|\d{4,})"#', "$key: vaste breedte-attribuut");
        expect($html)->not->toMatch('#(?<![-\w])width:\s*(?:[6-9]\d\d|5[7-9]\d|\d{4,})px#', "$key: vaste breedte in CSS");
    }
});

test('knopmails: fallback is korte linktekst; platte tekst bevat de volledige url', function () {
    $mails = MailRenderer::all();

    foreach (['bevestigen', 'bevestigen-herinnering', 'wachtwoord-reset', 'nieuw-emailadres-bevestigen'] as $key) {
        expect($mails[$key]['html'])->toContain('Werkt de knop niet? Gebruik dan');
        expect($mails[$key]['html'])->toContain('>deze link</a>');
        expect($mails[$key]['text'])->toMatch('#Werkt de knop niet\? Gebruik dan https?://\S+ in je browser\.#');
        expect($mails[$key]['text'])->not->toContain('](');
    }
});

test('mail-container is maximaal 600px en past zich aan de breedte aan', function () {
    $html = MailRenderer::all()['bevestigen']['html'];

    expect($html)->toMatch('#class="inner-body"[^>]*max-width: 600px#');
    expect($html)->toMatch('#class="footer"[^>]*max-width: 600px#');
});

test('dump mails voor screenshots (alleen met MAIL_RENDER_DIR)', function () {
    $dir = getenv('MAIL_RENDER_DIR');
    if (! is_string($dir) || $dir === '') {
        $this->markTestSkipped('MAIL_RENDER_DIR niet gezet');
    }
    @mkdir($dir, 0777, true);
    foreach (MailRenderer::all() as $key => $mail) {
        file_put_contents("$dir/$key.html", $mail['html']);
        file_put_contents("$dir/$key.txt", $mail['text']);
    }
    expect(true)->toBeTrue();
});
