<?php

namespace App\Http\Requests\Specialist;

use App\Domain\Specialists\Enums\AccountType;
use App\Domain\Specialists\Rules\ValidPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Walidacja sekcji "Dane firmy i opis" (`PUT /panel/profil`).
 *
 * Przyjmuje WYŁĄCZNIE: nazwę publiczną, rodzaj działalności, opis, telefon i zgodę na jego publikację.
 * Wszystkie inne pola (slug, publikacja, weryfikacja, promień...) są ignorowane, bo `validated()` zwraca tylko te klucze.
 */
class UpdateProfileRequest extends ProfileRequest
{
    /**
     * Ujednolica dane przed walidacją: obcięte spacje, znaki nowej linii jako LF (liczone raz do limitu znaków).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['display_name', 'phone'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('description'))) {
            $merge['description'] = str_replace(["\r\n", "\r"], "\n", $this->input('description'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Bez znaków sterujących i kierunkowych (BiDi, w \p{C}) oraz bez < i >: nazwa trafia do tytułu strony.
            'display_name' => ['required', 'string', 'max:'.config('specialists.display_name_max_length'), 'regex:/^[^<>\p{C}]+$/u'],
            'account_type' => ['required', Rule::in(AccountType::values())],
            'description' => ['nullable', 'string', 'max:'.config('specialists.description_max_length')],
            'phone' => ['nullable', 'string', new ValidPhone],
            'phone_is_public' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Zgoda na telefon publiczny wymaga podania telefonu.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('phone_is_public') && blank($this->input('phone'))) {
                    $validator->errors()->add('phone_is_public', __('specialist.validation.phone_required_for_public'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => __('specialist.validation.display_name_required'),
            'display_name.max' => __('specialist.validation.display_name_max', ['max' => config('specialists.display_name_max_length')]),
            'display_name.regex' => __('specialist.validation.display_name_chars'),
            'description.max' => __('specialist.validation.description_max', ['max' => config('specialists.description_max_length')]),
        ];
    }

    /**
     * Dane do zapisu (tylko dozwolone klucze).
     *
     * @return array{display_name: string, account_type: string, description: string|null, phone: string|null, phone_is_public: bool}
     */
    public function profileData(): array
    {
        return [
            'display_name' => (string) $this->validated('display_name'),
            'account_type' => (string) $this->validated('account_type'),
            'description' => $this->validated('description'),
            'phone' => $this->validated('phone'),
            'phone_is_public' => $this->boolean('phone_is_public'),
        ];
    }
}
