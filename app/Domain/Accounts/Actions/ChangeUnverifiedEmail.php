<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Jobs\ProcessPendingEmailChange;
use App\Models\User;
use App\Support\AuditLogger;
use DomainException;

/**
 * Prośba o zmianę adresu e-mail na koncie, którego adres nie został jeszcze potwierdzony
 * (np. literówka przy rejestracji). Zmiana adresu potwierdzonego jest poza zakresem.
 *
 * `users.email` NIE zmienia się w tym kroku: nowy adres trafia do `pending_email`, link potwierdzający
 * idzie na NOWY adres, a podmiana następuje po kliknięciu ({@see ConfirmPendingEmail}).
 * Ta metoda robi zawsze to samo, niezależnie od tego, czy adres ma konto (brak ujawniania kont):
 * zapis i zakolejkowanie zadania, które dopiero w kolejce rozróżnia adres wolny od zajętego.
 */
class ChangeUnverifiedEmail
{
    /**
     * Zapisuje oczekujący adres i kolejkuje wysyłkę.
     *
     * @param  string  $newEmail  Nowy adres (małymi literami).
     *
     * @throws DomainException Gdy adres konta jest już potwierdzony.
     */
    public function handle(User $user, string $newEmail): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new DomainException('Adres e-mail jest już potwierdzony.');
        }

        $user->forceFill(['pending_email' => $newEmail])->save();

        // Wpis bez informacji, czy adres był zajęty.
        AuditLogger::record('email_change_requested', causer: $user, subject: $user);

        ProcessPendingEmailChange::dispatch($user->getKey());
    }
}
