<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public static function findToken($token): ?static
    {
        if (str_starts_with($token, 'taskflow-') && strlen($token) >= 82) {
            $secret = substr($token, -64);

            return static::where('token', hash('sha256', $secret))->first();
        }

        return parent::findToken($token);
    }
}
