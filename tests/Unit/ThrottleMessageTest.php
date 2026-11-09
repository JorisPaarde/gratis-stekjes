<?php

declare(strict_types=1);

use App\Support\ThrottleMessage;

test('throttle melding met seconden', function () {
    expect(ThrottleMessage::forSeconds(1))->toBe('Even te veel pogingen. Probeer het over een seconde opnieuw.');
    expect(ThrottleMessage::forSeconds(45))->toBe('Even te veel pogingen. Probeer het over 45 seconden opnieuw.');
});

test('throttle melding met minuten en uren', function () {
    expect(ThrottleMessage::forSeconds(60))->toBe('Even te veel pogingen. Probeer het over een minuut opnieuw.');
    expect(ThrottleMessage::forSeconds(120))->toBe('Even te veel pogingen. Probeer het over 2 minuten opnieuw.');
    expect(ThrottleMessage::forSeconds(3599))->toBe('Even te veel pogingen. Probeer het over een uur opnieuw.');
    expect(ThrottleMessage::forSeconds(3600))->toBe('Even te veel pogingen. Probeer het over een uur opnieuw.');
    expect(ThrottleMessage::forSeconds(7200))->toBe('Even te veel pogingen. Probeer het over 2 uur opnieuw.');
});

test('mail-resend throttle melding is specifiek Nederlands', function () {
    expect(ThrottleMessage::forMailResend(1))->toBe('Je kunt over 1 seconde opnieuw een mail laten sturen.');
    expect(ThrottleMessage::forMailResend(45))->toBe('Je kunt over 45 seconden opnieuw een mail laten sturen.');
    expect(ThrottleMessage::forMailResend(60))->toBe('Je kunt over 1 minuut opnieuw een mail laten sturen.');
    expect(ThrottleMessage::forMailResend(3600))->toBe('Je kunt over 1 uur opnieuw een mail laten sturen.');
});
