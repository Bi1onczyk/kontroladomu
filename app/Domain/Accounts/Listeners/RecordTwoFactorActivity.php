<?php

namespace App\Domain\Accounts\Listeners;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEvent;

/**
 * Zapisuje w dzienniku aktywności zdarzenia 2FA (bez kodów i sekretów).
 */
class RecordTwoFactorActivity
{
    /**
     * Mapa zdarzenie Fortify -> nazwa wpisu w dzienniku.
     */
    private const EVENTS = [
        TwoFactorAuthenticationEnabled::class => 'two_factor_enabled',
        TwoFactorAuthenticationConfirmed::class => 'two_factor_confirmed',
        TwoFactorAuthenticationDisabled::class => 'two_factor_disabled',
        RecoveryCodesGenerated::class => 'recovery_codes_regenerated',
        RecoveryCodeReplaced::class => 'recovery_code_used',
    ];

    /**
     * Zapisuje wpis dla obsługiwanego zdarzenia.
     */
    public function handle(TwoFactorAuthenticationEvent|RecoveryCodesGenerated|RecoveryCodeReplaced $event): void
    {
        $name = self::EVENTS[$event::class] ?? null;

        if ($name !== null && $event->user instanceof Model) {
            AuditLogger::record($name, causer: $event->user, subject: $event->user);
        }
    }

    /**
     * Klasy zdarzeń, które ten listener obsługuje (do rejestracji w AppServiceProvider).
     *
     * @return list<class-string>
     */
    public static function events(): array
    {
        return array_keys(self::EVENTS);
    }
}
