<?php

use App\Domain\Accounts\Rules\ValidNip;
use Illuminate\Support\Facades\Validator;

it('reguła ValidNip odrzuca NIP złożony z jednej powtarzanej cyfry mimo poprawnej sumy kontrolnej', function (string $nip) {
    expect(Validator::make(['nip' => $nip], ['nip' => [new ValidNip]])->fails())->toBeTrue();
})->with(['0000000000', '1111111111']);

it('reguła ValidNip przyjmuje prawdziwy NIP z twardą spacją', function () {
    expect(Validator::make(['nip' => "693\u{00A0}117\u{2013}18-24"], ['nip' => [new ValidNip]])->passes())->toBeTrue();
});
