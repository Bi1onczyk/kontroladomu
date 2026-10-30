<?php

namespace App\Http\Resources;

use App\Domain\Geo\Models\TerytLocality;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pojedyncza podpowiedź miejscowości w odpowiedzi autouzupełniania.
 * Nie ujawnia wewnętrznych `id`, tylko `simc` (kod TERYT) i `slug`.
 *
 * @mixin TerytLocality
 */
class LocalityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $municipality = $this->municipality;

        return [
            'simc' => $this->simc,
            'name' => $this->name,
            'label' => __('geo.label', [
                'name' => $this->name,
                'municipality' => $municipality->name,
                'county' => $municipality->county_name,
                'voivodeship' => $municipality->voivodeship_name,
            ]),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'slug' => $this->slug,
            'municipality' => $municipality->name,
            'county' => $municipality->county_name,
            'voivodeship' => $municipality->voivodeship_name,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'has_coordinates' => $this->hasCoordinates(),
        ];
    }
}
