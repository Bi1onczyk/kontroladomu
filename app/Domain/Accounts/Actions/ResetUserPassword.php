<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Notifications\PasswordChangedNotification;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Ustawienie nowego hasła po kliknięciu w link z maila (wołane przez kontroler Fortify).
 *
 * Kończy wszystkie sesje użytkownika i zapisuje zdarzenie w dzienniku.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(private readonly RevokeUserSessions $revokeSessions) {}

    /**
     * Waliduje i zapisuje nowe hasło.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        $user->forceFill(['password' => Hash::make((string) $input['password'])])->save();

        $this->revokeSessions->handle($user);

        AuditLogger::record('password_reset', causer: $user, subject: $user);

        $user->notify(new PasswordChangedNotification);
    }
}
