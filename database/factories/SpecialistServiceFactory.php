<?php

namespace Database\Factories;

use App\Domain\Inspections\Enums\InspectionType;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Specialists\Models\SpecialistProfile;
use App\Domain\Specialists\Models\SpecialistService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialistService>
 */
class SpecialistServiceFactory extends Factory
{
    protected $model = SpecialistService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'specialist_profile_id' => SpecialistProfile::factory(),
            'inspection_type' => InspectionType::Chimney,
            'property_type' => PropertyType::SingleFamilyHouse,
            'price_grosze' => 15000,
        ];
    }

    /** Wycena indywidualna (brak ceny). */
    public function individual(): static
    {
        return $this->state(['price_grosze' => null]);
    }
}
