<?php

namespace App\Domain\Accounts\Rules;

use App\Domain\Accounts\Support\Nip;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Reguła walidacji NIP: format (10 cyfr po normalizacji) i suma kontrolna.
 */
class ValidNip implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = Nip::normalize(is_scalar($value) ? (string) $value : '');

        if (! preg_match('/^\d{10}$/', $normalized)) {
            $fail('validation.nip.digits')->translate();

            return;
        }

        // Same powtarzane cyfry (0000000000, 1111111111...) przechodzą sumę kontrolną, ale nie są prawdziwymi numerami.
        if (! Nip::isValid($normalized) || preg_match('/^(\d)\1{9}$/', $normalized) === 1) {
            $fail('validation.nip.checksum')->translate();
        }
    }
}
