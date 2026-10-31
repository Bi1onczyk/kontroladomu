<?php

namespace App\Domain\Specialists\Enums;

/**
 * Rodzaj działalności fachowca: osoba prowadząca działalność albo firma z pracownikami.
 */
enum AccountType: string
{
    case SoleProprietor = 'sole_proprietor';
    case Company = 'company';

    /**
     * Nazwa do formularza w panelu.
     */
    public function label(): string
    {
        return __('specialist.data.account_type_'.$this->value);
    }

    /**
     * Nazwa na stronie publicznej.
     */
    public function publicLabel(): string
    {
        return __('specialist.public.type_'.$this->value);
    }

    /**
     * Wszystkie wartości (do reguły `Rule::in`).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
