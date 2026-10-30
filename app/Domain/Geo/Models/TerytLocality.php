<?php

namespace App\Domain\Geo\Models;

use App\Domain\Geo\Enums\LocalityKind;
use Database\Factories\TerytLocalityFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Miejscowość z rejestru TERYT (plik SIMC), także wieś i część miejscowości. Źródło: GUS, rejestr TERYT.
 *
 * `simc` to kod TERYT miejscowości i klucz, którego będą używać nieruchomości. Współrzędne (`lat`, `lng`)
 * mogą być puste: SIMC ich nie zawiera, uzupełnia je geokodowanie po pierwszym użyciu miejscowości.
 * Miejscowości nie kasujemy (mogą się do nich odwoływać dane); znikającym z pliku ustawiamy `is_active = false`.
 *
 * @property int $id
 * @property string $simc
 * @property string|null $parent_simc
 * @property int $municipality_id
 * @property string $name
 * @property string $name_search
 * @property string $kind_code
 * @property LocalityKind $kind
 * @property bool $is_part
 * @property string $slug
 * @property string|null $lat
 * @property string|null $lng
 * @property string|null $coordinates_source
 * @property Carbon|null $geocoded_at
 * @property bool $is_active
 * @property-read TerytMunicipality $municipality
 */
#[UseFactory(TerytLocalityFactory::class)]
class TerytLocality extends Model
{
    /** @use HasFactory<TerytLocalityFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'simc',
        'parent_simc',
        'municipality_id',
        'name',
        'name_search',
        'kind_code',
        'kind',
        'is_part',
        'slug',
        'lat',
        'lng',
        'coordinates_source',
        'geocoded_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LocalityKind::class,
            'is_part' => 'boolean',
            'is_active' => 'boolean',
            'geocoded_at' => 'datetime',
        ];
    }

    /**
     * Gmina, do której należy miejscowość.
     *
     * @return BelongsTo<TerytMunicipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(TerytMunicipality::class, 'municipality_id');
    }

    /**
     * Czy miejscowość ma zapisane współrzędne.
     */
    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
