<?php

namespace App\Domain\Specialists\Enums;

/**
 * Stan weryfikacji fachowca przez administratora. Zmienia go wyłącznie zadanie 005;
 * w zadaniu 004 jest to tylko pole stanu (domyślnie `pending`).
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    /**
     * Polska nazwa stanu.
     */
    public function label(): string
    {
        return __('specialist.verification.'.$this->value);
    }

    /**
     * Czy profil w tym stanie może być widoczny publicznie (oczekujący i zweryfikowany).
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Pending || $this === self::Verified;
    }
}
