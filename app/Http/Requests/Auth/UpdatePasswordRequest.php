<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Zmiana hasła przez zalogowanego: obecne hasło + nowe (min. 12 znaków, nie z wycieku, inne niż obecne).
 */
class UpdatePasswordRequest extends FormRequest
{
    /**
     * Dostęp zapewnia middleware `auth`.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::default(), 'different:current_password'],
        ];
    }
}
