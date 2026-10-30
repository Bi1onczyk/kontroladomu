<?php

use App\Domain\Accounts\Rules\ValidNip;
use App\Domain\Accounts\Support\Nip;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// Walidator potrzebuje kontenera aplikacji.
uses(TestCase::class);

it('NIP: znane poprawne numery przechodzą sumę kontrolną', function (string $nip) {
    expect(Nip::isValid($nip))->toBeTrue();
})->with(['6931171824', '5252248481', '1234563218', '7740001454']);

it('NIP: zmiana jednej cyfry psuje sumę kontrolną', function () {
    $valid = '6931171824';
    $broken = 0;
    for ($i = 0; $i < 10; $i++) {
        $copy = $valid;
        $copy[$i] = (string) (((int) $copy[$i] + 1) % 10);
        $broken += Nip::isValid($copy) ? 0 : 1;
    }

    expect($broken)->toBe(10);
});

it('NIP: reszta 10 z dzielenia przez 11 jest zawsze niepoprawna', function () {
    // 1234567890 daje sumę 6+10+21+8+15+24+35+48+63=230, 230 % 11 = 10.
    expect(Nip::isValid('1234567890'))->toBeFalse();
});

it('NIP: zła długość, litery, pusty i null są odrzucane', function (?string $value) {
    expect(Nip::isValid($value))->toBeFalse();
})->with([null, '', '   ', '693117182', '69311718244', '69311718AB', 'abcdefghij', '693.117.18.24']);

it('NIP: normalizacja usuwa spacje, myślniki i PL, ale nie zmienia innych znaków', function () {
    expect(Nip::normalize('PL 693-117-18-24'))->toBe('6931171824')
        ->and(Nip::normalize("693\t117\n18 24"))->toBe('6931171824')
        ->and(Nip::normalize('pl6931171824'))->toBe('6931171824')
        ->and(Nip::normalize('69311718A4'))->toBe('69311718A4')
        ->and(Nip::normalize(null))->toBe('');
});

it('NIP: przyjmuje cyfry po twardej spacji (kopiowanie ze stron WWW)', function () {
    expect(Nip::isValid("693\u{00A0}117\u{00A0}18\u{00A0}24"))->toBeTrue();
});

it('NIP: same zera i same jedynki przechodzą sumę kontrolną, ale nie są prawdziwymi numerami (informacja)', function () {
    // Algorytm sumy kontrolnej akceptuje te ciągi; test dokumentuje zachowanie i pilnuje, by zmiana była świadoma.
    expect(Nip::isValid('0000000000'))->toBeTrue()
        ->and(Nip::isValid('1111111111'))->toBeTrue();
});

it('reguła ValidNip zwraca komunikaty z lang/pl dla złego formatu i złej sumy', function () {
    $format = Validator::make(['nip' => 'abc'], ['nip' => [new ValidNip]]);
    $sum = Validator::make(['nip' => '6931171825'], ['nip' => [new ValidNip]]);

    expect($format->fails())->toBeTrue()->and($sum->fails())->toBeTrue()
        ->and($format->errors()->first('nip'))->not->toContain('validation.')
        ->and($sum->errors()->first('nip'))->not->toContain('validation.')
        ->and($format->errors()->first('nip'))->not->toBe($sum->errors()->first('nip'));
});

it('reguła ValidNip nie rzuca wyjątku dla nie-tekstu', function () {
    foreach ([[], 123, 6931171824, null] as $value) {
        $validator = Validator::make(['nip' => $value], ['nip' => [new ValidNip]]);
        expect(fn () => $validator->fails())->not->toThrow(Throwable::class);
    }
});
