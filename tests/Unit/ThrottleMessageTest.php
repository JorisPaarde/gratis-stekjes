<?php

declare(strict_types=1);

use App\Support\ThrottleMessage;

test('throttle melding met seconden', function () {
    expect(ThrottleMessage::forSeconds(1))->toBe('Te veel pogingen. Probeer het over 1 seconde opnieuw.');
    expect(ThrottleMessage::forSeconds(45))->toBe('Te veel pogingen. Probeer het over 45 seconden opnieuw.');
});

test('throttle melding met minuten en uren', function () {
    expect(ThrottleMessage::forSeconds(60))->toBe('Te veel pogingen. Probeer het over 1 minuut opnieuw.');
    expect(ThrottleMessage::forSeconds(120))->toBe('Te veel pogingen. Probeer het over 2 minuten opnieuw.');
    expect(ThrottleMessage::forSeconds(3600))->toBe('Te veel pogingen. Probeer het over 1 uur opnieuw.');
    expect(ThrottleMessage::forSeconds(7200))->toBe('Te veel pogingen. Probeer het over 2 uur opnieuw.');
});
