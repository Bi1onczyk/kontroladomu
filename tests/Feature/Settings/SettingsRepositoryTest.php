<?php

use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('zapisuje i odczytuje wartość jawną oraz wylicza grupę z klucza', function () {
    $repo = app(SettingsRepository::class);

    $repo->put('teryt.simc_state_date', '2026-10-30');

    expect($repo->get('teryt.simc_state_date'))->toBe('2026-10-30');
    expect(Setting::query()->where('key', 'teryt.simc_state_date')->value('group'))->toBe('teryt');
});

it('zwraca wartość domyślną dla brakującego klucza', function () {
    expect(app(SettingsRepository::class)->get('nie.ma', 'domyslna'))->toBe('domyslna');
});

it('unieważnia cache po zapisie', function () {
    $repo = app(SettingsRepository::class);
    $repo->put('geocoding.test', 'a');
    expect($repo->get('geocoding.test'))->toBe('a');

    $repo->put('geocoding.test', 'b');

    expect($repo->get('geocoding.test'))->toBe('b');
});

it('szyfruje sekret w bazie, a odczyt zwraca jawny tekst', function () {
    $repo = app(SettingsRepository::class);

    $repo->putSecret('mail.password', 'tajne-haslo-smtp');

    $raw = DB::table('settings')->where('key', 'mail.password')->value('secret_value');
    expect($raw)->not->toContain('tajne-haslo-smtp');
    expect($repo->getSecret('mail.password'))->toBe('tajne-haslo-smtp');
});

it('nie trzyma sekretu w cache ani w serializacji modelu', function () {
    $repo = app(SettingsRepository::class);
    $repo->putSecret('mail.password', 'tajne-haslo-smtp');
    $repo->get('mail.password');

    expect(json_encode(Cache::get('settings.all')))->not->toContain('tajne-haslo-smtp');
    expect(Setting::query()->firstOrFail()->toJson())->not->toContain('secret_value');
});

it('zwraca wartość domyślną, gdy sekretu nie da się odszyfrować', function () {
    DB::table('settings')->insert([
        'group' => 'mail', 'key' => 'mail.password', 'secret_value' => 'to-nie-jest-szyfrogram',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(app(SettingsRepository::class)->getSecret('mail.password', 'brak'))->toBe('brak');
});

it('zapamiętuje autora zmiany i usuwa ustawienie', function () {
    $repo = app(SettingsRepository::class);
    $user = User::factory()->create();

    $repo->put('mail.host', 'smtp.example.pl', $user->id);
    expect(Setting::query()->where('key', 'mail.host')->value('updated_by'))->toBe($user->id);

    $repo->forget('mail.host');
    expect($repo->get('mail.host'))->toBeNull();
});
