<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Walidacja zapytania autouzupełniania miejscowości (`GET /miejscowosci/szukaj`).
 *
 * Endpoint jest publiczny (dane TERYT nie są poufne), więc autoryzacja zawsze przechodzi;
 * ochronę przed nadużyciem zapewnia limit żądań (`throttle:locality-search`).
 */
class SearchLocalitiesRequest extends FormRequest
{
    /**
     * Publiczny endpoint: każdy (także gość) może wyszukiwać.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Endpoint zwraca wyłącznie JSON, więc błąd walidacji ma być zawsze odpowiedzią 422 w JSON,
     * także gdy klient nie wysłał nagłówka `Accept: application/json` (inaczej Laravel zrobiłby przekierowanie).
     * Format jak w standardowej odpowiedzi Laravela: `message` i `errors`.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Obcina białe znaki z zapytania przed walidacją (długość liczona po `trim`).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $this->merge(['q' => trim($this->query('q'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:60', "regex:/^[\\p{L}\\p{N} \\-.,']+$/u"],
            'limit' => ['nullable', 'integer', 'between:1,15'],
            'include_parts' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.regex' => __('geo.validation.q_format'),
        ];
    }

    /**
     * Zapytanie po walidacji.
     */
    public function searchText(): string
    {
        return (string) $this->validated('q');
    }

    /**
     * Maksymalna liczba wyników (domyślnie 10).
     */
    public function resultLimit(): int
    {
        return (int) ($this->validated('limit') ?? 10);
    }

    /**
     * Czy uwzględnić części miejscowości (osiedla, przysiółki).
     */
    public function includeParts(): bool
    {
        return (bool) ($this->validated('include_parts') ?? false);
    }
}
