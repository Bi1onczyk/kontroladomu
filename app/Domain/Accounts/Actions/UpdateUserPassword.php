<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Notifications\PasswordChangedNotification;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Zmiana hasła przez zalogowanego użytkownika (znającego obecne hasło).
 *
 * Wylogowuje pozostałe urządzenia (bieżąca sesja zostaje), wysyła powiadomienie e-mail.
 */
class UpdateUserPassword
{
    public function __construct(private readonly RevokeUserSessions $revokeSessions) {}

    /**
     * @param  string  $newPassword  Nowe hasło (już zwalidowane przez UpdatePasswordRequest).
     * @param  string|null  $currentSessionId  Identyfikator bieżącej sesji, która ma zostać zachowana.
     */
    public function handle(User $user, string $newPassword, ?string $currentSessionId): void
    {
        DB::transaction(function () use ($user, $newPassword, $currentSessionId) {
            $user->forceFill(['password' => $newPassword])->save();
            $this->revokeSessions->handle($user, $currentSessionId);
        });

        AuditLogger::record('password_changed', causer: $user, subject: $user);

        $user->notify(new PasswordChangedNotification);
    }
}
