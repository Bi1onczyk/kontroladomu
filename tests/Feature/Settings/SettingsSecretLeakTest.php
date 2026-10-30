<?php

/*
| Testy szczelności sekretów w ustawieniach (tester, zadanie 003): sekret nie może trafić do cache, logów,
| dziennika aktywności ani serializacji modelu. Uzupełnia SettingsRepositoryTest.
*/

use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

const EDGE_SECRET = 'SUPER-TAJNE-HASLO-SMTP-12345';

it('zapis i odczyt sekretu nie wpisuje go do logów aplikacji', function () {
    Log::spy();
    $repo = app(SettingsRepository::class);

    $repo->putSecret('mail.password', EDGE_SECRET);
    $repo->getSecret('mail.password');
    $repo->get('mail.password');
    $repo->forget('mail.password');

    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
});

it('zapis sekretu nie tworzy wpisu w dzienniku aktywności', function () {
    $before = DB::table('activity_log')->count();

    app(SettingsRepository::class)->putSecret('mail.password', EDGE_SECRET, User::factory()->create()->id);

    $dump = json_encode(DB::table('activity_log')->get()->all());
    expect(DB::table('activity_log')->where('subject_type', Setting::class)->count())->toBe(0)
        ->and($dump)->not->toContain(EDGE_SECRET);
    // Utworzenie użytkownika mogło dodać własny wpis; ważne, że nie ma w nim sekretu ani modelu Setting.
    expect(DB::table('activity_log')->count())->toBeGreaterThanOrEqual($before);
});

it('sekret nie występuje jawnie w żadnej kolumnie tabeli settings ani w cache po wielokrotnym odczycie', function () {
    $repo = app(SettingsRepository::class);
    $repo->put('mail.host', 'smtp.example.pl');
    $repo->putSecret('mail.password', EDGE_SECRET);

    foreach (range(1, 3) as $i) {
        $repo->get('mail.host');
        $repo->getSecret('mail.password');
    }

    $rows = json_encode(DB::table('settings')->get()->all(), JSON_UNESCAPED_UNICODE);
    expect($rows)->not->toContain(EDGE_SECRET)
        ->and(serialize(Cache::get('settings.all')))->not->toContain(EDGE_SECRET);
});

it('model ukrywa sekret w toArray, toJson, jsonSerialize i rzutowaniu na tekst', function () {
    app(SettingsRepository::class)->putSecret('mail.password', EDGE_SECRET);
    $setting = Setting::query()->firstOrFail();

    expect(json_encode($setting->toArray()))->not->toContain(EDGE_SECRET)
        ->and($setting->toJson())->not->toContain(EDGE_SECRET)
        ->and(json_encode($setting))->not->toContain(EDGE_SECRET)
        ->and((string) $setting)->not->toContain(EDGE_SECRET)
        ->and(json_encode(Setting::query()->get()))->not->toContain(EDGE_SECRET);
});

it('wyjątek przy nieudanym odszyfrowaniu nie ujawnia zaszyfrowanej zawartości', function () {
    $encrypted = encrypt(EDGE_SECRET);
    DB::table('settings')->insert([
        'group' => 'mail', 'key' => 'mail.password', 'secret_value' => 'zmieniony-'.$encrypted,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Zmieniony szyfrogram (uszkodzony MAC) daje wartość domyślną, a nie wyjątek ze szczegółami.
    expect(app(SettingsRepository::class)->getSecret('mail.password', 'brak'))->toBe('brak');
});

it('zmiana wartości jawnej nie kasuje sekretu tego samego klucza, a zapis sekretu nie kasuje wartości jawnej', function () {
    $repo = app(SettingsRepository::class);

    $repo->putSecret('mail.password', EDGE_SECRET);
    $repo->put('mail.password', 'opis');
    expect($repo->getSecret('mail.password'))->toBe(EDGE_SECRET)->and($repo->get('mail.password'))->toBe('opis');

    $repo->putSecret('mail.password', 'nowe-haslo');
    expect($repo->get('mail.password'))->toBe('opis')->and($repo->getSecret('mail.password'))->toBe('nowe-haslo');
});

it('forget usuwa jednocześnie wartość jawną i sekret, a ponowny zapis tworzy jeden wiersz', function () {
    $repo = app(SettingsRepository::class);
    $repo->put('mail.password', 'a');
    $repo->putSecret('mail.password', EDGE_SECRET);

    $repo->forget('mail.password');

    expect($repo->getSecret('mail.password'))->toBeNull()
        ->and($repo->get('mail.password'))->toBeNull()
        ->and(Setting::query()->count())->toBe(0);

    $repo->put('mail.password', 'b');
    $repo->put('mail.password', 'c');
    expect(Setting::query()->where('key', 'mail.password')->count())->toBe(1);
});

it('zapis sekretu null kasuje sekret, a pusty ciąg jest zapisany jako pusty', function () {
    $repo = app(SettingsRepository::class);
    $repo->putSecret('mail.password', EDGE_SECRET);

    $repo->putSecret('mail.password', null);

    expect($repo->getSecret('mail.password', 'domyslny'))->toBe('domyslny');
});

it('obsługuje polskie znaki, emoji i bardzo długi tekst w wartości jawnej i w sekrecie', function () {
    $repo = app(SettingsRepository::class);
    $long = str_repeat('Zażółć gęślą jaźń 😀 ', 500);

    $repo->put('geocoding.note', $long);
    $repo->putSecret('mail.password', $long);

    expect($repo->get('geocoding.note'))->toBe($long)
        ->and($repo->getSecret('mail.password'))->toBe($long);
});

it('klucz jest unikalny: wielokrotny zapis tego samego klucza nie tworzy dubletów', function () {
    $repo = app(SettingsRepository::class);

    foreach (range(1, 5) as $i) {
        $repo->put('teryt.source', "wartość {$i}");
    }

    expect(Setting::query()->where('key', 'teryt.source')->count())->toBe(1)
        ->and($repo->get('teryt.source'))->toBe('wartość 5');
});

it('usunięcie użytkownika, który zmienił ustawienie, zeruje updated_by (nie kasuje ustawienia)', function () {
    $user = User::factory()->create();
    app(SettingsRepository::class)->put('mail.host', 'smtp.example.pl', $user->id);

    DB::table('users')->where('id', $user->id)->delete();

    $setting = Setting::query()->where('key', 'mail.host')->firstOrFail();
    expect($setting->updated_by)->toBeNull()->and($setting->value)->toBe('smtp.example.pl');
});

it('mass assignment: nie da się nadpisać identyfikatora ani znaczników czasu przez fill', function () {
    $setting = new Setting;
    $setting->fill(['id' => 999, 'key' => 'a.b', 'created_at' => '2000-01-01 00:00:00']);

    expect($setting->id)->toBeNull()
        ->and($setting->getAttribute('created_at'))->toBeNull();
});
