<?php

namespace App\Domain\Specialists\Rules;

use App\Domain\Specialists\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Polski numer telefonu: 9 cyfr albo +48 i 9 cyfr (spacje i myślniki dozwolone). Zapis w formacie +48XXXXXXXXX
 * wykonuje akcja przez {@see PhoneNumber::normalize()}.
 */
final class ValidPhone implements ValidationRule
{
    /**
     * Odrzuca wartość, która nie jest polskim numerem telefonu.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNumber::normalize($value) === null) {
            $fail(__('specialist.validation.phone_format'));
        }
    }
}
