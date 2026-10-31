<?php

use App\Domain\Geo\Actions\ImportTerytData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| UWAGA: dawny helper fakeViews() (podstawiał puste widoki w miejsce prawdziwych) został usunięty.
| Widoki istnieją, więc testy mają renderować PRAWDZIWE szablony - inaczej błąd w Blade
| (brakujący klucz, zła zmienna) zostałby niezauważony.
*/

/** Hasło spełniające reguły (min. 12 znaków), używane w testach. */
const TEST_PASSWORD = 'zielony-dach-w-lutym';

/**
 * Sesja z świeżo potwierdzonym hasłem (omija przekierowanie na /potwierdz-haslo).
 *
 * @return array<string, int>
 */
function passwordConfirmed(): array
{
    return ['auth.password_confirmed_at' => time()];
}

/**
 * Aktualny kod TOTP dla konta z prawdziwym (zapisanym przez Fortify) sekretem.
 */
function currentTotp(User $user): string
{
    $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);

    return app(Google2FA::class)->getCurrentOtp($secret);
}

/** Aktualny kod TOTP dla sekretu z UserFactory::withTwoFactor(). */
function factoryTotp(): string
{
    return app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP');
}

/**
 * Poprawne dane formularza rejestracji właściciela (współdzielone przez testy).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ownerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Anna Nowak',
        'email' => 'Anna@Example.pl',
        'password' => 'zielony-dach-w-lutym',
        'terms' => '1',
    ], $overrides);
}

/**
 * Poprawne dane formularza rejestracji fachowca.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function specialistPayload(array $overrides = []): array
{
    return ownerPayload(array_merge([
        'company_name' => 'Kominiarz Jan',
        'nip' => '693-117-18-24',
        'business_declaration' => '1',
    ], $overrides));
}

/**
 * Wykonuje akcję i zwraca liczbę zapytań SQL (bez zapytań transakcyjnych typu BEGIN/COMMIT/SAVEPOINT).
 */
function countQueries(callable $action): int
{
    $count = 0;
    DB::listen(function ($query) use (&$count) {
        if (! preg_match('/^(begin|commit|rollback|savepoint|release)/i', trim($query->sql))) {
            $count++;
        }
    });

    $action();

    return $count;
}

/**
 * Importuje SYNTETYCZNE pliki TERC/SIMC z tests/Fixtures/teryt (24 miejscowości, 7 gmin) do bazy testowej.
 */
function importTerytFixture(): void
{
    app(ImportTerytData::class)->handle(
        base_path('tests/Fixtures/teryt/TERC.csv'),
        base_path('tests/Fixtures/teryt/SIMC.csv'),
    );
}
