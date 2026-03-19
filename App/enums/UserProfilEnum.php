<?php

namespace App\Enums;

class UserProfileEnum
{
    const SUPER_ADMIN = 'Super Admin';

    /**
     * Retourne toutes les valeurs des profils
     *
     * @return array
     */
    public static function values(): array
    {
        return [
            self::SUPER_ADMIN,
        ];
    }
}