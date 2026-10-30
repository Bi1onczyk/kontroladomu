<?php

namespace App\Domain\Accounts\Actions;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Potwierdzenie NOWEGO adresu e-mail po kliknięciu w link (zmiana adresu przed potwierdzeniem konta).
 *
 * Dopiero tu `users.email` jest podmieniany, a adres uznany za potwierdzony. Unikalność sprawdzamy
 * ponownie w transakcji: jeśli w międzyczasie ktoś zajął adres, użytkownik widzi neutralny ekran
 * "link nieważny" (bez informacji, że adres ma konto). Link nie loguje.
 */
class ConfirmPendingEmail
{
    /**
     * @param  string  $id  Identyfikator konta z linku.
     * @param  string  $hash  Skrót nowego adresu z linku.
     * @return array{0: string, 1: User|null} Wynik (VerifyEmailAddress::VERIFIED / INVALID) i konto (null przy INVALID).
     */
    public function handle(string $id, string $hash): array
    {
        if (! ctype_digit($id)) {
            return [VerifyEmailAddress::INVALID, null];
        }

        try {
            $user = DB::transaction(function () use ($id, $hash): ?User {
                $user = User::query()->lockForUpdate()->find((int) $id);
                $pending = $user?->pending_email;

                if ($user === null || $pending === null || $user->hasVerifiedEmail()
                    || ! hash_equals(sha1($pending), $hash)) {
                    return null;
                }

                $taken = User::query()->where('email', $pending)->whereKeyNot($user->getKey())->exists();

                if ($taken) {
                    // Adres zajęty w międzyczasie: porzucamy prośbę, ekran jest neutralny.
                    $user->forceFill(['pending_email' => null])->save();

                    return null;
                }

                $user->forceFill([
                    'email' => $pending,
                    'pending_email' => null,
                    'email_verified_at' => now(),
                ])->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Wyścig na poziomie bazy: traktujemy jak zajęty adres.
            return [VerifyEmailAddress::INVALID, null];
        }

        if ($user === null) {
            return [VerifyEmailAddress::INVALID, null];
        }

        event(new Verified($user));
        AuditLogger::record('email_changed_verified', causer: $user, subject: $user);

        return [VerifyEmailAddress::VERIFIED, $user];
    }
}
