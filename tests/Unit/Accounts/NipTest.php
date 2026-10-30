<?php

use App\Domain\Accounts\Support\Nip;

it('normalizuje NIP do samych cyfr', function () {
    expect(Nip::normalize('693-117-18-24'))->toBe('6931171824')
        ->and(Nip::normalize(' PL 693 117 18 24 '))->toBe('6931171824');
});

it('akceptuje poprawny NIP z sumą kontrolną', function () {
    expect(Nip::isValid('6931171824'))->toBeTrue()
        ->and(Nip::isValid('693-117-18-24'))->toBeTrue();
});

it('odrzuca NIP z błędną sumą, złą długością lub literami', function () {
    expect(Nip::isValid('6931171825'))->toBeFalse()
        ->and(Nip::isValid('123'))->toBeFalse()
        ->and(Nip::isValid('69311718AB'))->toBeFalse()
        ->and(Nip::isValid(null))->toBeFalse();
});
