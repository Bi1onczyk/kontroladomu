<?php

namespace App\Domain\Accounts\Actions;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Verified;

/**
 * Potwierdzenie adresu e-mail po kliknięciu w link z wiadomości.
 *
 * Link jest podpisany i działa bez sesji (nie loguje użytkownika). Skrót `hash` to sha1 adresu,
 * więc po zmianie adresu stary link przestaje działać.
 */
class VerifyEmailAddress
{
    public const VERIFIED = 'verified';

    public const ALREADY = 'already';

    public const INVALID = 'invalid';

    /**
     * Potwierdza adres wskazanego konta.
     *
     * @param  string  $id  Identyfikator konta z linku.
     * @param  string  $hash  Skrót adresu z linku.
     * @return array{0: string, 1: User|null} Wynik (VERIFIED / ALREADY / INVALID) i konto (null przy INVALID).
     */
    public function handle(string $id, string $hash): array
    {
        $user = ctype_digit($id) ? User::query()->find((int) $id) : null;

        // Gdy czeka zmiana adresu, link do poprzedniego adresu przestaje działać.
        if ($user === null || $user->pending_email !== null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return [self::INVALID, null];
        }

        if ($user->hasVerifiedEmail()) {
            return [self::ALREADY, $user];
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            AuditLogger::record('email_verified', causer: $user, subject: $user);
        }

        return [self::VERIFIED, $user];
    }
}
