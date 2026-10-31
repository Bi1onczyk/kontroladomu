<?php

use App\Domain\Geo\Enums\LocalityKind;
use App\Domain\Geo\Support\SearchText;

it('normalizuje polskie znaki, wielkość liter i interpunkcję', function (string $input, string $expected) {
    expect(SearchText::normalize($input))->toBe($expected);
})->with([
    ['Dąbrówka', 'dabrowka'],
    ['DĄBRÓWKA', 'dabrowka'],
    ['Łódź-Bałuty', 'lodz baluty'],
    ['  Nowy   Dwór  ', 'nowy dwor'],
    ['Żółć, gęślą jaźń', 'zolc gesla jazn'],
    ['Mazewo Dworskie"B"', 'mazewo dworskie b'],
    ['100% _test_ \\ ', '100 test'],
    ['', ''],
    ['---', ''],
]);

it('mapuje kody RM z SIMC na rodzaj miejscowości', function (string $rm, LocalityKind $kind) {
    expect(LocalityKind::fromSimcCode($rm))->toBe($kind);
})->with([
    ['00', LocalityKind::Part],
    ['99', LocalityKind::Part],
    ['01', LocalityKind::Village],
    ['02', LocalityKind::Colony],
    ['03', LocalityKind::Hamlet],
    ['04', LocalityKind::Settlement],
    ['05', LocalityKind::ForestSettlement],
    ['95', LocalityKind::District],
    ['98', LocalityKind::District],
    ['96', LocalityKind::City],
    ['06', LocalityKind::Other],
    ['07', LocalityKind::Other],
    ['77', LocalityKind::Other],
]);
