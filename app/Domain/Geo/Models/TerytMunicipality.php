<?php

namespace App\Domain\Geo\Models;

use Database\Factories\TerytMunicipalityFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gmina z rejestru TERYT (plik TERC). Źródło: GUS, rejestr TERYT.
 *
 * Nazwy powiatu i województwa są zdenormalizowane (ok. 4 tys. wierszy), żeby etykietę
 * "gm. X, pow. Y, woj. Z" zbudować bez złączeń. Dane odświeża komenda `teryt:import`.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type_code
 * @property string $county_code
 * @property string $county_name
 * @property string $county_name_search
 * @property string $voivodeship_code
 * @property string $voivodeship_name
 * @property string $name_search
 */
#[UseFactory(TerytMunicipalityFactory::class)]
class TerytMunicipality extends Model
{
    /** @use HasFactory<TerytMunicipalityFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'type_code',
        'county_code',
        'county_name',
        'county_name_search',
        'voivodeship_code',
        'voivodeship_name',
        'name_search',
    ];

    /**
     * Miejscowości należące do gminy.
     *
     * @return HasMany<TerytLocality, $this>
     */
    public function localities(): HasMany
    {
        return $this->hasMany(TerytLocality::class, 'municipality_id');
    }
}
