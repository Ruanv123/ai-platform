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
