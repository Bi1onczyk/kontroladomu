<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Notifications\TwoFactorResetNotification;
use App\Domain\Accounts\Support\TwoFactorLockout;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reset 2FA użytkownika przez administratora (utrata telefonu i kodów awaryjnych).
 *
 * Czyści sekret, kody i potwierdzenie, kończy wszystkie sesje, odblokowuje licznik błędnych kodów,
 * wysyła powiadomienie e-mail i zapisuje wpis w dzienniku administratora (kto, dlaczego, kiedy).
 */
class ResetTwoFactor
{
    public function __construct(
        private readonly RevokeUserSessions $revokeSessions,
        private readonly TwoFactorLockout $lockout,
    ) {}

    /**
     * Wykonuje reset.
     *
     * @param  string  $reason  Powód (np. numer zgłoszenia i sposób weryfikacji tożsamości); wymagany.
     */
    public function handle(User $user, string $reason): void
    {
        // Mail wysyłamy tylko, gdy konto miało skonfigurowane 2FA (inaczej nie ma czego resetować).
        $hadTwoFactor = $user->two_factor_secret !== null || $user->two_factor_confirmed_at !== null;

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $this->revokeSessions->handle($user);
        });

        $this->lockout->clear($user->getKey());

        AuditLogger::record(
            'two_factor_reset',
            subject: $user,
            properties: ['reason' => $reason],
            log: AuditLogger::LOG_ADMIN,
        );

        if ($hadTwoFactor) {
            $user->notify(new TwoFactorResetNotification($user->requiresTwoFactor()));
        }
    }
}
