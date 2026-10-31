<?php

declare(strict_types=1);

use App\Support\Country;
use App\Support\PostalCode;

test('NL postcode normaliseert en formatteert 1234 AB', function () {
    expect(PostalCode::normalize('1234 AB', Country::NL))->toBe('1234AB')
        ->and(PostalCode::normalize('1234ab', Country::NL))->toBe('1234AB')
        ->and(PostalCode::normalize('1234', Country::NL))->toBe('1234')
        ->and(PostalCode::format('1234AB', Country::NL))->toBe('1234 AB')
        ->and(PostalCode::format('1234', Country::NL))->toBe('1234')
        ->and(PostalCode::validate('1234 AB', Country::NL))->toBeTrue()
        ->and(PostalCode::validate('1234', Country::NL))->toBeTrue()
        ->and(PostalCode::validate('123AB', Country::NL))->toBeString();
});

test('BE postcode accepteert heel België 1000–9999', function (string $pc) {
    expect(PostalCode::isBelgianPostcode($pc))->toBeTrue()
        ->and(PostalCode::validate($pc, Country::BE))->toBeTrue()
        ->and(PostalCode::normalize($pc, Country::BE))->toBe($pc)
        ->and(PostalCode::format($pc, Country::BE))->toBe($pc);
})->with(['1000', '1299', '1300', '1500', '2000', '3000', '4000', '5000', '7000', '8000', '9000', '9999']);

test('BE postcode weigert ongeldig formaat', function () {
    expect(PostalCode::validate('900', Country::BE))->toBeString()
        ->and(PostalCode::validate('90000', Country::BE))->toBeString()
        ->and(PostalCode::validate('ABCD', Country::BE))->toBeString()
        ->and(PostalCode::normalize('40 00', Country::BE))->toBeNull();
});

test('Country normaliseert naar NL of BE', function () {
    expect(Country::normalize('be'))->toBe('BE')
        ->and(Country::normalize(''))->toBe('NL')
        ->and(Country::normalize('DE'))->toBe('NL')
        ->and(Country::label('BE'))->toBe('België')
        ->and(Country::isValid('NL'))->toBeTrue()
        ->and(Country::isValid('BE'))->toBeTrue()
        ->and(Country::isValid('FR'))->toBeFalse();
});
