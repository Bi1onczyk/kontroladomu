<?php

namespace App\Domain\Accounts\Support;

use App\Domain\Accounts\Notifications\RegisterAttemptNotification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Wspólna obsługa "ktoś próbował użyć adresu, który ma już konto" (rejestracja i zmiana adresu).
 *
 * Napastnik nie może odróżnić adresu wolnego od zajętego po odpowiedzi serwera, dlatego zajęty adres
 * nie daje błędu: właściciel konta dostaje wiadomość (maks. 1 na godzinę na adres), a czas odpowiedzi
 * jest wyrównany haszowaniem hasła.
 */
class TakenEmailNotifier
{
    public function __construct(private readonly RecipientMailLimiter $limiter) {}

    /**
     * Powiadamia właściciela konta o próbie użycia jego adresu.
     */
    public function notify(User $existing): void
    {
        // Wyrównanie czasu: w ścieżce "nowe konto" jest haszowanie hasła.
        Hash::make('wyrownanie-czasu-odpowiedzi');

        $this->limiter->send(
            $existing->email,
            'attempt',
            fn () => $existing->notify(new RegisterAttemptNotification),
            1,
        );
    }
}
