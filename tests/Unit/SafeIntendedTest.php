<?php

declare(strict_types=1);

use App\Support\SafeIntended;
use Illuminate\Http\Request;

test('SafeIntended accepteert relatieve same-site paden en weigert protocol-relatief', function () {
    expect(SafeIntended::sanitize('/account/verenigingen'))->toBe('/account/verenigingen')
        ->and(SafeIntended::isAllowed('/account/verenigingen'))->toBeTrue()
        ->and(SafeIntended::sanitize('//evil.example'))->toBe('/account')
        ->and(SafeIntended::isAllowed('//evil.example'))->toBeFalse()
        ->and(SafeIntended::sanitize('/\\evil'))->toBe('/account');
});

test('SafeIntended::peek zonder session store gooit niet (mail/CLI)', function () {
    $request = Request::create('/account/bevestiging-nodig', 'GET');
    expect($request->hasSession())->toBeFalse()
        ->and(SafeIntended::peek($request))->toBeNull();

    SafeIntended::normalizeSession($request);
    SafeIntended::remember($request, SafeIntended::ASSOCIATION);
    expect(SafeIntended::peek($request))->toBeNull();
});

test('SafeIntended absolute URL zonder request-root valt terug op default', function () {
    // Unit: geen HTTP-request-root → geen false-positive herkomst via app.url.
    expect(SafeIntended::sanitize('https://example.test/account/verenigingen'))->toBe('/account')
        ->and(SafeIntended::isAllowed('https://example.test/account/verenigingen'))->toBeFalse();
});