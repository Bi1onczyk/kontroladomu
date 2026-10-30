<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Tworzy konto administratora (wywoływane wyłącznie komendą `admin:create`).
 *
 * Adres e-mail jest uznany za potwierdzony (operator ma dostęp do serwera). Bez NIP i bez zgód
 * marketingowych. 2FA zostanie wymuszone przy pierwszym logowaniu przez bramkę.
 */
class CreateAdminAccount
{
    /**
     * @param  string  $name  Imię i nazwisko.
     * @param  string  $email  Adres e-mail (małymi literami, wolny).
     * @param  string  $password  Hasło (zwalidowane przez komendę).
     */
    public function handle(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            UserRole::ensureExist();

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'terms_accepted_at' => now(),
                'terms_version' => config('legal.terms_version'),
                'privacy_accepted_at' => now(),
                'privacy_version' => config('legal.privacy_version'),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(UserRole::Admin->value);

            AuditLogger::record('admin_created', subject: $user, log: AuditLogger::LOG_ADMIN);

            return $user;
        });
    }
}
