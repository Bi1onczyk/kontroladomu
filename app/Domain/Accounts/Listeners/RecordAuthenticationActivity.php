<?php

namespace App\Domain\Accounts\Listeners;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Zapisuje w dzienniku aktywności udane logowania i wylogowania.
 *
 * Nie zapisujemy nieudanych logowań z adresem e-mail (tylko licznik w cache).
 * Logowanie jest zapisywane wyłącznie przy formularzu logowania (nie przy odtworzeniu sesji z ciasteczka).
 */
class RecordAuthenticationActivity
{
    /**
     * Udane logowanie hasłem (lub hasłem + kodem 2FA).
     */
    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User && request()->routeIs('login.store', 'two-factor.login.store')) {
            AuditLogger::record('login', causer: $event->user, subject: $event->user, properties: [
                'remember' => $event->remember,
            ]);
        }
    }

    /**
     * Wylogowanie.
     */
    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuditLogger::record('logout', causer: $event->user, subject: $event->user);
        }
    }
}
