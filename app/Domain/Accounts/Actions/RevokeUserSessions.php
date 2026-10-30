<?php

namespace App\Domain\Accounts\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kończy sesje użytkownika (po zmianie hasła, resecie hasła lub 2FA).
 *
 * Usuwa wiersze tabeli `sessions` (driver `database`) i zmienia token "zapamiętaj mnie".
 */
class RevokeUserSessions
{
    /**
     * @param  string|null  $exceptSessionId  Sesja do zachowania (bieżąca przy zmianie hasła przez zalogowanego).
     */
    public function handle(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }

        // Wersja sesji: middleware RejectRevokedSessions kończy każdą sesję ze starą wersją (niezależnie od sterownika).
        $user->forceFill([
            'remember_token' => Str::random(60),
            'session_version' => (int) $user->session_version + 1,
        ])->save();
    }
}
