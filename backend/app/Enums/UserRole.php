<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';

    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::User => 'User',
        };
    }
}
