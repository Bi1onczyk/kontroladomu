<?php

namespace Database\Factories;

use App\Domain\Geo\Enums\LocalityKind;
use App\Domain\Geo\Models\TerytLocality;
use App\Domain\Geo\Models\TerytMunicipality;
use App\Domain\Geo\Support\SearchText;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TerytLocality>
 */
class TerytLocalityFactory extends Factory
{
    protected $model = TerytLocality::class;

    /**
     * Dane SYNTETYCZNE (nie pochodzą z rejestru TERYT).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->lastName();
        $simc = fake()->unique()->numerify('9######');

        return [
            'simc' => $simc,
            'parent_simc' => null,
            'municipality_id' => TerytMunicipality::factory(),
            'name' => $name,
            'name_search' => SearchText::normalize($name),
            'kind_code' => '01',
            'kind' => LocalityKind::Village,
            'is_part' => false,
            'slug' => Str::slug($name).'-'.$simc,
            'is_active' => true,
        ];
    }

    /** Miasto (RM 96). */
    public function city(): static
    {
        return $this->state(['kind_code' => '96', 'kind' => LocalityKind::City]);
    }

    /** Część miejscowości (RM 00). */
    public function part(): static
    {
        return $this->state(['kind_code' => '00', 'kind' => LocalityKind::Part, 'is_part' => true]);
    }

    /** Miejscowość ze znanymi współrzędnymi. */
    public function withCoordinates(float $lat = 52.3, float $lng = 16.6): static
    {
        return $this->state(['lat' => $lat, 'lng' => $lng, 'coordinates_source' => 'manual', 'geocoded_at' => now()]);
    }
}
