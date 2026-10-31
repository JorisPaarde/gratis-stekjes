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

test('BE postcode accepteert Vlaanderen en Brussel-reeksen', function (string $pc) {
    expect(PostalCode::isFlandersPostcode($pc))->toBeTrue()
        ->and(PostalCode::validate($pc, Country::BE))->toBeTrue()
        ->and(PostalCode::normalize($pc, Country::BE))->toBe($pc)
        ->and(PostalCode::format($pc, Country::BE))->toBe($pc);
})->with(['1000', '1299', '1500', '2000', '3000', '3999', '8000', '9000', '9999']);

test('BE postcode weigert Wallonië en uitgesloten reeksen', function (string $pc) {
    expect(PostalCode::isExcludedBelgianPostcode($pc))->toBeTrue()
        ->and(PostalCode::isFlandersPostcode($pc))->toBeFalse()
        ->and(PostalCode::validate($pc, Country::BE))->toBe(PostalCode::OUTSIDE_FLANDERS_MESSAGE);
})->with(['1300', '1400', '1499', '4000', '5000', '7000', '7999']);

test('Country normaliseert naar NL of BE', function () {
    expect(Country::normalize('be'))->toBe('BE')
        ->and(Country::normalize(''))->toBe('NL')
        ->and(Country::normalize('DE'))->toBe('NL')
        ->and(Country::isValid('NL'))->toBeTrue()
        ->and(Country::isValid('BE'))->toBeTrue()
        ->and(Country::isValid('FR'))->toBeFalse();
});
