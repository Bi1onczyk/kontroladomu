<?php

namespace App\Http\Requests\Specialist;

use App\Domain\Inspections\Enums\InspectionType;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Specialists\Support\Grosze;
use Illuminate\Contracts\Validation\Validator;

/**
 * Walidacja cennika (`PUT /panel/profil/uslugi`).
 *
 * Kształt formularza: `services[{rodzaj przeglądu}][{typ nieruchomości}][enabled|price|individual]`, np.
 * `services[chimney][apartment][enabled]=1`, `services[chimney][apartment][price]=180,50` albo `...[individual]=1`.
 * Klucze muszą być wartościami enumów `InspectionType` i `PropertyType`. Pozycja zaznaczona (`enabled`) wymaga ceny
 * (1 zł do 50 000 zł) albo "wyceny indywidualnej". Pozycje niezaznaczone są ignorowane.
 */
class UpdateServicesRequest extends ProfileRequest
{
    /** @var list<array{inspection_type: string, property_type: string, price_grosze: int|null}> */
    private array $rows = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'services' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'services.required' => __('specialist.validation.services_required'),
            'services.array' => __('specialist.validation.services_required'),
        ];
    }

    /**
     * Sprawdza zawartość zagnieżdżonej tablicy (klucze z enumów, ceny) i buduje listę pozycji do zapisu.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $services = $this->input('services');

                if (! is_array($services)) {
                    return;
                }

                $rows = [];

                foreach ($services as $inspection => $byProperty) {
                    $inspectionType = is_string($inspection) ? InspectionType::tryFrom($inspection) : null;

                    if ($inspectionType === null || ! is_array($byProperty)) {
                        $validator->errors()->add('services', __('specialist.validation.services_required'));

                        continue;
                    }

                    foreach ($byProperty as $property => $item) {
                        $propertyType = is_string($property) ? PropertyType::tryFrom($property) : null;

                        if ($propertyType === null || ! is_array($item)) {
                            $validator->errors()->add('services', __('specialist.validation.services_required'));

                            continue;
                        }

                        if (! filter_var($item['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                            continue;
                        }

                        $field = "services.{$inspectionType->value}.{$propertyType->value}.price";
                        $price = $this->priceFor($item, $field, $validator);

                        if ($price !== false) {
                            $rows[] = [
                                'inspection_type' => $inspectionType->value,
                                'property_type' => $propertyType->value,
                                'price_grosze' => $price,
                            ];
                        }
                    }
                }

                if ($rows === [] && $validator->errors()->isEmpty()) {
                    $validator->errors()->add('services', __('specialist.validation.services_required'));
                }

                $this->rows = $rows;
            },
        ];
    }

    /**
     * Cena pozycji w groszach, null dla wyceny indywidualnej; false (z błędem walidacji) dla ceny niepoprawnej.
     *
     * @param  array<mixed>  $item
     */
    private function priceFor(array $item, string $field, Validator $validator): int|false|null
    {
        if (filter_var($item['individual'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $raw = $item['price'] ?? null;

        if (! is_string($raw) && ! is_int($raw)) {
            $validator->errors()->add($field, __('specialist.validation.price_required'));

            return false;
        }

        if (trim((string) $raw) === '') {
            $validator->errors()->add($field, __('specialist.validation.price_required'));

            return false;
        }

        $grosze = Grosze::parse((string) $raw);

        if ($grosze === null) {
            $validator->errors()->add($field, __('specialist.validation.price_format'));

            return false;
        }

        $min = (int) config('specialists.price_min_grosze');
        $max = (int) config('specialists.price_max_grosze');

        if ($grosze < $min || $grosze > $max) {
            $validator->errors()->add($field, __('specialist.validation.price_range', [
                'min' => Grosze::format($min),
                'max' => Grosze::format($max),
            ]));

            return false;
        }

        return $grosze;
    }

    /**
     * Pozycje do zapisu (dostępne po udanej walidacji).
     *
     * @return list<array{inspection_type: string, property_type: string, price_grosze: int|null}>
     */
    public function serviceRows(): array
    {
        return $this->rows;
    }
}
