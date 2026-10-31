<?php

namespace App\Domain\Specialists\Models;

use App\Domain\Inspections\Enums\InspectionType;
use App\Domain\Properties\Enums\PropertyType;
use Database\Factories\SpecialistServiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pozycja cennika: fachowiec wykonuje dany rodzaj przeglądu dla danego typu nieruchomości.
 * `price_grosze` (brutto, w groszach) równe NULL oznacza "wycena indywidualna".
 *
 * @property int $id
 * @property int $specialist_profile_id
 * @property InspectionType $inspection_type
 * @property PropertyType $property_type
 * @property int|null $price_grosze
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SpecialistProfile $profile
 */
#[UseFactory(SpecialistServiceFactory::class)]
class SpecialistService extends Model
{
    /** @use HasFactory<SpecialistServiceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'specialist_profile_id',
        'inspection_type',
        'property_type',
        'price_grosze',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspection_type' => InspectionType::class,
            'property_type' => PropertyType::class,
            'price_grosze' => 'integer',
        ];
    }

    /**
     * Profil, do którego należy pozycja.
     *
     * @return BelongsTo<SpecialistProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SpecialistProfile::class, 'specialist_profile_id');
    }
}
