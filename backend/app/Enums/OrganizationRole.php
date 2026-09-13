<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Admin = 'admin';

    case Editor = 'editor';

    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }
}
