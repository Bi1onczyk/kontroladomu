<?php

namespace Database\Factories;

use App\Domain\Geo\Models\TerytMunicipality;
use App\Domain\Geo\Support\SearchText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerytMunicipality>
 */
class TerytMunicipalityFactory extends Factory
{
    protected $model = TerytMunicipality::class;

    /**
     * Dane SYNTETYCZNE (nie pochodzą z rejestru TERYT).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();
        $county = 'testowy';

        return [
            'code' => fake()->unique()->numerify('3#####2'),
            'name' => $name,
            'type_code' => '2',
            'county_code' => '3001',
            'county_name' => $county,
            'county_name_search' => SearchText::normalize($county),
            'voivodeship_code' => '30',
            'voivodeship_name' => 'wielkopolskie',
            'name_search' => SearchText::normalize($name),
        ];
    }
}
