<?php

namespace App\Http\Requests\Auth;

use App\Domain\Accounts\Data\RegisterAccountData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Support\Nip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Bazowa walidacja rejestracji. E-mail celowo BEZ reguły `unique`: zajęty adres nie może być
 * ujawniany (obsługuje to akcja RegisterAccount).
 */
abstract class RegisterRequest extends FormRequest
{
    /**
     * Rola nadawana przy rejestracji.
     */
    abstract public function role(): UserRole;

    /**
     * Dostęp: formularz jest publiczny (ochrona: limiter, Turnstile).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizacja przed walidacją: e-mail małymi literami, NIP do samych cyfr.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('email'))) {
            $merge['email'] = Str::lower(trim($this->input('email')));
        }

        if (is_string($this->input('nip'))) {
            $merge['nip'] = Nip::normalize($this->input('nip'));
        }

        $this->merge($merge);
    }

    /**
     * Reguły wspólne dla obu rodzajów kont.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', Password::default()],
            'terms' => ['accepted'],
            // Zgoda marketingowa jest osobna i nieobowiązkowa (RODO).
            'marketing' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Zamienia zwalidowane dane na obiekt przekazywany do akcji.
     */
    public function toData(): RegisterAccountData
    {
        $data = $this->validated();

        return new RegisterAccountData(
            role: $this->role(),
            name: trim((string) $data['name']),
            email: (string) $data['email'],
            password: (string) $data['password'],
            companyName: isset($data['company_name']) ? trim((string) $data['company_name']) : null,
            nip: isset($data['nip']) ? (string) $data['nip'] : null,
            marketingConsent: $this->boolean('marketing'),
        );
    }
}
