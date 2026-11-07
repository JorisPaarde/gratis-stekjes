<?php

declare(strict_types=1);

use App\Support\SafeIntended;

test('SafeIntended accepteert relatieve same-site paden en weigert protocol-relatief', function () {
    expect(SafeIntended::sanitize('/account/verenigingen'))->toBe('/account/verenigingen')
        ->and(SafeIntended::isAllowed('/account/verenigingen'))->toBeTrue()
        ->and(SafeIntended::sanitize('//evil.example'))->toBe('/account')
        ->and(SafeIntended::isAllowed('//evil.example'))->toBeFalse()
        ->and(SafeIntended::sanitize('/\\evil'))->toBe('/account');
});
