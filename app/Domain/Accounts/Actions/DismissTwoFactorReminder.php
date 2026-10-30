<?php

namespace App\Domain\Accounts\Actions;

use App\Models\User;

/**
 * Odkłada baner z zachętą do włączenia 2FA (właściciel nieruchomości) o 30 dni.
 */
class DismissTwoFactorReminder
{
    /**
     * Zapisuje datę, do której baner jest ukryty.
     */
    public function handle(User $user): void
    {
        $user->forceFill(['two_factor_reminder_until' => now()->addDays(30)])->save();
    }
}
