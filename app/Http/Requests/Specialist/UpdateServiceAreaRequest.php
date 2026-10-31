<?php

namespace App\Http\Requests\Specialist;

use App\Domain\Specialists\Support\Grosze;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Walidacja sekcji "Obszar działania i dojazd" (`PUT /panel/profil/obszar`), S8.
 *
 * Pola: `locality_simc` (aktywna miejscowość z TERYT), `service_radius_km`, `travel_rate_per_km` (stawka w ZŁOTYCH,
 * np. "1,50"; zapisywana w groszach) i `travel_free_km`. Warunek `travel_free_km <= service_radius_km` jest sprawdzany
 * na wartościach z tego samego formularza. Pusta stawka i puste km gratis oznaczają 0.
 */
class UpdateServiceAreaRequest extends ProfileRequest
{
    private int $rateGrosze = 0;

    /**
     * Puste pola dojazdu to 0 (stawka nieustawiona).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['travel_rate_per_km', 'travel_free_km'] as $field) {
            if (! $this->filled($field)) {
                $merge[$field] = '0';
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $min = (int) config('specialists.radius_min_km');
        $max = (int) config('specialists.radius_max_km');

        return [
            'locality_simc' => ['required', 'string', 'size:7', Rule::exists('teryt_localities', 'simc')->where('is_active', true)],
            'service_radius_km' => ['required', 'integer', 'between:'.$min.','.$max],
            'travel_rate_per_km' => ['required', 'string'],
            'travel_free_km' => ['required', 'integer', 'min:0', 'lte:service_radius_km'],
        ];
    }

    /**
     * Przelicza stawkę ze złotych na grosze i pilnuje zakresu.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $grosze = Grosze::parse((string) $this->input('travel_rate_per_km'));
                $max = (int) config('specialists.travel_rate_max_grosze');

                if ($grosze === null || $grosze > $max) {
                    $validator->errors()->add('travel_rate_per_km', __('specialist.validation.travel_rate_range', ['max' => Grosze::format($max)]));

                    return;
                }

                $this->rateGrosze = $grosze;
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locality_simc.required' => __('specialist.validation.locality_required'),
            'locality_simc.size' => __('specialist.validation.locality_unknown'),
            'locality_simc.exists' => __('specialist.validation.locality_unknown'),
            'service_radius_km.required' => __('specialist.validation.radius_range', ['min' => config('specialists.radius_min_km'), 'max' => config('specialists.radius_max_km')]),
            'service_radius_km.integer' => __('specialist.validation.radius_range', ['min' => config('specialists.radius_min_km'), 'max' => config('specialists.radius_max_km')]),
            'service_radius_km.between' => __('specialist.validation.radius_range', ['min' => config('specialists.radius_min_km'), 'max' => config('specialists.radius_max_km')]),
            'travel_free_km.lte' => __('specialist.validation.travel_free_km_max', ['radius' => $this->input('service_radius_km')]),
        ];
    }

    /**
     * Dane do zapisu (stawka w groszach).
     *
     * @return array{locality_simc: string, service_radius_km: int, travel_rate_per_km_grosze: int, travel_free_km: int}
     */
    public function areaData(): array
    {
        return [
            'locality_simc' => (string) $this->validated('locality_simc'),
            'service_radius_km' => (int) $this->validated('service_radius_km'),
            'travel_rate_per_km_grosze' => $this->rateGrosze,
            'travel_free_km' => (int) $this->validated('travel_free_km'),
        ];
    }
}
