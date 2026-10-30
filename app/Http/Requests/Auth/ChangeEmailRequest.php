<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Zmiana adresu e-mail na koncie niepotwierdzonym. Celowo BEZ reguły `unique`: zajęty adres
 * nie może być ujawniany (obsługuje to akcja ChangeUnverifiedEmail).
 */
class ChangeEmailRequest extends FormRequest
{
    /**
     * Tylko zalogowany z niepotwierdzonym adresem (policzone też w kontrolerze).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ! $user->hasVerifiedEmail();
    }

    /**
     * Normalizacja: małe litery.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email:rfc', 'max:190',
                // Własny, aktualny adres konta: informacja dotyczy tylko zalogowanego, nic nie ujawnia.
                Rule::notIn([Str::lower((string) $this->user()?->email)]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.not_in' => __('auth.email_change.same'),
        ];
    }
}
