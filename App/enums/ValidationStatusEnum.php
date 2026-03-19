<?php

namespace App\enums;

class ValidationStatusEnum
{
    const NON_VALIDE = 'Non validé';
    const VALIDE = 'Validé';
    const EN_ATTENTE = 'En attente';

    public static function values(): array
    {
        return [
            self::NON_VALIDE,
            self::VALIDE,
            self::EN_ATTENTE,
        ];
    }

    public static function labels(): array
    {
        return [
            self::NON_VALIDE => 'Refusé',
            self::VALIDE => 'Validé en 2025',
            self::EN_ATTENTE => 'En attente',
        ];
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::values(), true);
    }
}
