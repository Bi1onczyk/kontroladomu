<?php

namespace App\Domain\Accounts\Listeners;

use App\Domain\Accounts\Support\LoginFailureCounter;
use Illuminate\Auth\Events\Login;

/**
 * Po udanym logowaniu zeruje licznik nieudanych prób TEGO KONTA.
 * Licznik adresu IP zostaje (wygasa po 15 minutach), żeby napastnik nie mógł go zerować własnym kontem.
 */
class ClearLoginFailures
{
    public function __construct(private readonly LoginFailureCounter $counter) {}

    /**
     * Kasuje licznik konta, które właśnie się zalogowało.
     */
    public function handle(Login $event): void
    {
        $email = $event->user->email ?? null;

        if (is_string($email)) {
            $this->counter->clearAccount($email);
        }
    }
}
