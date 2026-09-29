<?php

use App\Support\E164PhoneNumber;

it('formats phone numbers as E.164', function (string $phone, ?string $expected) {
    expect(E164PhoneNumber::format($phone))->toBe($expected);
})->with([
    'Brazilian mobile without ninth digit' => ['553186441470', '+5531986441470'],
    'Brazilian mobile with ninth digit' => ['5531986441470', '+5531986441470'],
    'Brazilian mobile with punctuation' => ['+55 (31) 98644-1470', '+5531986441470'],
    'Brazilian landline' => ['553132221470', '+553132221470'],
    'non-Brazilian number' => ['14155550123', '+14155550123'],
    'too short' => ['1234567', null],
    'too long' => ['1234567890123456', null],
    'leading zero' => ['0553186441470', null],
    'not a phone' => ['playground', null],
]);

it('returns E.164 digits without the plus sign as the provider recipient', function () {
    expect(E164PhoneNumber::recipient('553186441470'))->toBe('5531986441470')
        ->and(E164PhoneNumber::recipient('playground'))->toBe('playground');
});

it('normalizes a national phone number using the Shop country', function (string $phone, string $country, ?string $expected) {
    expect(E164PhoneNumber::formatForCountry($phone, $country))->toBe($expected);
})->with([
    'Brazilian local mobile with punctuation' => ['(11) 99999-8888', 'BR', '+5511999998888'],
    'Brazilian mobile with carrier prefix' => ['0 21 11 99999-8888', 'BR', '+5511999998888'],
    'Brazilian landline without ninth digit' => ['(31) 3222-1470', 'BR', '+553132221470'],
    'United Kingdom national prefix' => ['020 7946 0123', 'GB', '+442079460123'],
    'United States national phone' => ['415-555-0123', 'US', '+14155550123'],
    'Explicit international phone' => ['+1 (415) 555-0123', 'BR', '+14155550123'],
    'Unknown country preserves generic normalization' => ['14155550123', 'ZZ', '+14155550123'],
    'Invalid phone' => ['1234567', 'BR', null],
]);
