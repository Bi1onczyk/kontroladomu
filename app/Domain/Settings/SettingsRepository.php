<?php

namespace App\Domain\Settings;

use App\Domain\Settings\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;

/**
 * Jedyne miejsce dostępu do tabeli `settings` (ADR-015).
 *
 * Cała tabela (wartości JAWNE) jest trzymana w cache pod kluczem `settings.all` i unieważniana przy każdym
 * zapisie, więc odczyt ustawienia nie robi osobnego zapytania do bazy. Sekrety nie trafiają do cache:
 * są czytane z bazy i odszyfrowywane przez model. Repozytorium nie zapisuje niczego do dziennika aktywności;
 * może to zrobić wywołujący, ale wyłącznie informację, że klucz się zmienił (nigdy wartość).
 */
class SettingsRepository
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Zwraca wartość jawną ustawienia albo `$default`, gdy klucza brak lub wartość jest NULL (pusty ciąg "" zwracany jest bez zmian).
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Zwraca odszyfrowany sekret albo `$default`. Zwraca też `$default`, gdy odszyfrowanie się nie udaje
     * (np. po zmianie APP_KEY): błąd nie może przewrócić strony, a administrator wpisze sekret ponownie.
     */
    public function getSecret(string $key, ?string $default = null): ?string
    {
        try {
            $secret = Setting::query()->where('key', $key)->first()?->secret_value;
        } catch (DecryptException) {
            return $default;
        }

        return $secret ?? $default;
    }

    /**
     * Zapisuje wartość jawną (tworzy lub aktualizuje wiersz) i unieważnia cache.
     *
     * @param  int|null  $userId  Kto zmienił ustawienie (administrator); null dla zmian systemowych (import, komenda).
     */
    public function put(string $key, ?string $value, ?int $userId = null): void
    {
        $setting = Setting::query()->firstOrNew(['key' => $key]);
        $setting->fill([
            'group' => $this->groupOf($key),
            'value' => $value,
            'updated_by' => $userId,
        ])->save();

        $this->flush();
    }

    /**
     * Zapisuje sekret (szyfrowany w bazie) i unieważnia cache.
     */
    public function putSecret(string $key, ?string $secret, ?int $userId = null): void
    {
        $setting = Setting::query()->firstOrNew(['key' => $key]);
        $setting->fill([
            'group' => $this->groupOf($key),
            'secret_value' => $secret,
            'updated_by' => $userId,
        ])->save();

        $this->flush();
    }

    /**
     * Usuwa ustawienie (wartość jawną i sekret) i unieważnia cache.
     */
    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        $this->flush();
    }

    /**
     * Unieważnia cache wszystkich ustawień.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Wszystkie wartości jawne (klucz => wartość) z cache.
     *
     * @return array<string, string|null>
     */
    private function all(): array
    {
        /** @var array<string, string|null> */
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->pluck('value', 'key')
            ->all());
    }

    /**
     * Grupa to pierwszy człon klucza kropkowanego (`mail.host` -> `mail`).
     */
    private function groupOf(string $key): string
    {
        return explode('.', $key, 2)[0];
    }
}
