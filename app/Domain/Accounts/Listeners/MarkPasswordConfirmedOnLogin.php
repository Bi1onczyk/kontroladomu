<?php

namespace App\Domain\Accounts\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * Po zalogowaniu formularzem oznacza hasło jako potwierdzone w sesji, żeby świeżo zalogowany
 * użytkownik nie musiał od razu podawać go ponownie przy włączaniu 2FA
 * (okno `auth.password_timeout`, 15 minut).
 */
class MarkPasswordConfirmedOnLogin
{
    /**
     * Ustawia znacznik czasu potwierdzenia hasła (tylko przy formularzu logowania).
     */
    public function handle(Login $event): void
    {
        if (request()->routeIs('login.store', 'two-factor.login.store')) {
            request()->session()->put('auth.password_confirmed_at', now()->getTimestamp());
        }
    }
}
