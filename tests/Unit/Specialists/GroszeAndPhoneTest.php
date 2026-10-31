<?php

use App\Domain\Specialists\Support\Grosze;
use App\Domain\Specialists\Support\PhoneNumber;

it('zamienia kwotę z formularza na grosze', function (string $input, ?int $expected) {
    expect(Grosze::parse($input))->toBe($expected);
})->with([
    'całe złote' => ['180', 18000],
    'przecinek' => ['180,50', 18050],
    'kropka' => ['180.5', 18050],
    'jedna cyfra po przecinku' => ['0,5', 50],
    'spacje i zł' => [' 1 200 zł ', 120000],
    'spacja twarda' => ["1\u{00A0}200", 120000],
    'zero' => ['0', 0],
    'litery' => ['sto', null],
    'trzy miejsca po przecinku' => ['1,234', null],
    'ujemna' => ['-5', null],
    'pusta' => ['', null],
    'dwa przecinki' => ['1,2,3', null],
    'notacja naukowa' => ['1e3', null],
]);

it('formatuje grosze jako tekst do pola formularza', function () {
    expect(Grosze::format(18000))->toBe('180')
        ->and(Grosze::format(18050))->toBe('180,50')
        ->and(Grosze::format(5))->toBe('0,05')
        ->and(Grosze::format(0))->toBe('0')
        ->and(Grosze::formatWithCurrency(18050))->toBe("180,50\u{00A0}zł");
});

it('normalizuje polski numer telefonu do +48XXXXXXXXX', function (string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'dziewięć cyfr' => ['600123456', '+48600123456'],
    'ze spacjami' => ['600 123 456', '+48600123456'],
    'z prefiksem' => ['+48 600-123-456', '+48600123456'],
    'z nawiasem' => ['(61) 123 45 67', '+48611234567'],
    'za krótki' => ['60012345', null],
    'za długi' => ['6001234567', null],
    'zero na początku' => ['012345678', null],
    'litery' => ['abc123456', null],
    'inny kraj' => ['+49 600123456', null],
]);

it('pokazuje numer w czytelnej postaci', function () {
    expect(PhoneNumber::display('+48600123456'))->toBe('600 123 456');
});
