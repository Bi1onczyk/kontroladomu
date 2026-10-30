<?php

namespace App\Domain\Accounts\Listeners;

use App\Domain\Accounts\Support\LoginFailureCounter;
use Illuminate\Auth\Events\Failed;

/**
 * Zlicza nieudane logowania z adresu IP i na konto (po progu formularz wymaga Turnstile).
 */
class CountLoginFailure
{
    public function __construct(private readonly LoginFailureCounter $counter) {}

    /**
     * Zwiększa liczniki dla IP bieżącego żądania i dla konta, na które była próba.
     */
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->counter->hit((string) request()->ip(), is_string($email) ? $email : null);
    }
}
