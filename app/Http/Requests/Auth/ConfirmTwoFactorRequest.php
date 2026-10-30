<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kod z aplikacji potwierdzający konfigurację 2FA (krok 2 kreatora).
 */
class ConfirmTwoFactorRequest extends FormRequest
{
    /**
     * Dostęp zapewniają middleware `auth`, `verified` i `password.confirm`.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Usuwa spacje z kodu (aplikacje pokazują kod w grupach "123 456").
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', $this->input('code'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:12'],
        ];
    }
}
