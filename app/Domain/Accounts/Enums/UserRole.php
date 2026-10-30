<?php

namespace App\Domain\Accounts\Enums;

use Spatie\Permission\Models\Role;

/**
 * Role użytkowników serwisu (guard `web`, przechowywane w tabelach Spatie Permission).
 *
 * Jedno konto może mieć kilka ról; o wyglądzie pulpitu decyduje rola główna
 * ({@see self::priority()}). Brak uprawnień szczegółowych: decyduje rola + Policy.
 */
enum UserRole: string
{
    case Owner = 'owner';
    case Specialist = 'specialist';
    case Technician = 'technician';
    case Admin = 'admin';

    /**
     * Czy rola wymaga obowiązkowego 2FA (CLAUDE.md §8: admin i właściciel konta fachowca).
     */
    public function requiresTwoFactor(): bool
    {
        return match ($this) {
            self::Admin, self::Specialist => true,
            self::Owner, self::Technician => false,
        };
    }

    /**
     * Czy rolę można wybrać samodzielnie przy rejestracji.
     */
    public function isRegistrable(): bool
    {
        return $this === self::Owner || $this === self::Specialist;
    }

    /**
     * Polska nazwa roli do wyświetlenia (lang/pl/roles.php).
     */
    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    /**
     * Role w kolejności ważności: pierwsza dopasowana do konta jest rolą główną.
     *
     * @return list<self>
     */
    public static function priority(): array
    {
        return [self::Admin, self::Specialist, self::Technician, self::Owner];
    }

    /**
     * Tworzy brakujące role w bazie (idempotentnie), żeby nie trzeba było pamiętać o seedzie.
     */
    public static function ensureExist(): void
    {
        foreach (self::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
