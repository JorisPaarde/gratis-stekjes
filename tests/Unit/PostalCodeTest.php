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

test('BE postcode normaliseert spaties en weigert ongeldig formaat', function () {
    expect(PostalCode::normalize('40 00', Country::BE))->toBe('4000')
        ->and(PostalCode::validate('900', Country::BE))->toBeString()
        ->and(PostalCode::validate('90000', Country::BE))->toBeString()
        ->and(PostalCode::validate('ABCD', Country::BE))->toBeString();
});

test('gebied haalt 4 cijfers uit NL en BE postcodes', function () {
    expect(PostalCode::gebied('1234 AB', Country::NL))->toBe('1234')
        ->and(PostalCode::gebied('1234AB', Country::NL))->toBe('1234')
        ->and(PostalCode::gebied('9000', Country::BE))->toBe('9000')
        ->and(PostalCode::gebied('abc', Country::NL))->toBeNull();
});

test('publicLabel is postcodegebied + plaats, nooit volledige postcode', function () {
    expect(PostalCode::publicLabel('1326', 'Almere'))->toBe('1326 Almere')
        ->and(PostalCode::publicLabel('3370', 'Boutersem'))->toBe('3370 Boutersem')
        ->and(PostalCode::publicLabel(null, 'Haarlem'))->toBe('Haarlem')
        ->and(PostalCode::publicLabel('2011', null))->toBe('2011')
        ->and(PostalCode::publicLabel('2011AB', 'Haarlem'))->toBe('Haarlem') // gebied moet 4 cijfers zijn
        ->and(PostalCode::locationLabel('2037 KB', Country::NL, 'Adlerstraat, 2037KB Haarlem', 'Haarlem'))->toBe('2037 Haarlem')
        ->and(PostalCode::locationLabel('Haarlem', Country::NL, 'Haarlem'))->toBe('Haarlem');
});

test('plaatsFromGeoLabel haalt woonplaats zonder straat', function () {
    expect(PostalCode::plaatsFromGeoLabel('Adlerstraat, 2037KB Haarlem'))->toBe('Haarlem')
        ->and(PostalCode::plaatsFromGeoLabel('2037KB, Haarlem'))->toBe('Haarlem')
        ->and(PostalCode::plaatsFromGeoLabel('1326 Almere'))->toBe('Almere')
        ->and(PostalCode::plaatsFromGeoLabel('9000'))->toBe('');
});

test('looksLikeAddressWithNumber herkent half adres, niet postcode+plaats', function () {
    expect(PostalCode::looksLikeAddressWithNumber('Kerkstraat 12'))->toBeTrue()
        ->and(PostalCode::looksLikeAddressWithNumber('Grote Markt 1A'))->toBeTrue()
        ->and(PostalCode::looksLikeAddressWithNumber('1326 Almere'))->toBeFalse()
        ->and(PostalCode::looksLikeAddressWithNumber('Haarlem'))->toBeFalse()
        ->and(PostalCode::looksLikeAddressWithNumber('2037'))->toBeFalse();
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
