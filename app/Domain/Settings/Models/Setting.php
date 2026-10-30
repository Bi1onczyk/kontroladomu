<?php

namespace App\Domain\Settings\Models;

use App\Domain\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Pojedyncze ustawienie aplikacji (klucz kropkowany, np. `teryt.simc_state_date`).
 *
 * Wartość wrażliwa trafia do `secret_value` i jest szyfrowana kluczem APP_KEY (cast `encrypted`).
 * Zmiana APP_KEY uniemożliwia odczyt sekretów (to samo ryzyko co przy 2FA, ADR-005).
 * Z modelu korzystaj wyłącznie przez {@see SettingsRepository}.
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string|null $secret_value
 * @property int|null $updated_by
 */
class Setting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
        'secret_value',
        'updated_by',
    ];

    /** Sekret nigdy nie trafia do serializacji (np. przypadkowy zrzut do JSON-a lub logu). */
    protected $hidden = [
        'secret_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret_value' => 'encrypted',
        ];
    }
}
