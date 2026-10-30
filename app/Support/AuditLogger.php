<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Opakowanie na spatie/laravel-activitylog: zapisuje zdarzenia bezpieczeństwa
 * z adresem IP i user-agentem (lub z danymi operatora, gdy zdarzenie pochodzi z konsoli).
 *
 * Nigdy nie przekazuj tu haseł, kodów, sekretów, tokenów ani adresów e-mail z nieudanych logowań.
 */
class AuditLogger
{
    /** Nazwa dziennika dla zwykłych zdarzeń kont (retencja 90 dni). */
    public const LOG_SECURITY = 'security';

    /** Nazwa dziennika dla działań administratorów (retencja 12 miesięcy). */
    public const LOG_ADMIN = 'security_admin';

    /**
     * Zapisuje wpis w dzienniku.
     *
     * @param  string  $event  Krótka nazwa zdarzenia, np. `registered`, `login`, `two_factor_reset`.
     * @param  Model|null  $causer  Kto wykonał (null = system / konsola).
     * @param  Model|null  $subject  Na kim lub na czym wykonano.
     * @param  array<string, mixed>  $properties  Dodatkowe dane (bez sekretów).
     * @param  string  $log  Nazwa dziennika (self::LOG_*).
     */
    public static function record(
        string $event,
        ?Model $causer = null,
        ?Model $subject = null,
        array $properties = [],
        string $log = self::LOG_SECURITY,
    ): void {
        $properties += self::context();

        $activity = activity($log)->event($event)->withProperties($properties);

        if ($causer !== null) {
            $activity->causedBy($causer);
        }

        if ($subject !== null) {
            $activity->performedOn($subject);
        }

        $activity->log($event);
    }

    /**
     * Dane kontekstu: IP i user-agent żądania HTTP albo operator i host dla konsoli.
     *
     * @return array<string, string|null>
     */
    private static function context(): array
    {
        // Brak trasy = zdarzenie z konsoli (komenda artisan), nie z żądania HTTP.
        if (request()->route() === null && app()->runningInConsole()) {
            return [
                'operator' => get_current_user() ?: null,
                'host' => gethostname() ?: null,
            ];
        }

        /** @var Request $request */
        $request = request();

        return [
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ];
    }
}
