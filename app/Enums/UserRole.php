<?php

namespace App\Enums;

enum UserRole: string
{
    case Superuser = 'superuser';
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Superuser => 'スーパーユーザー',
            self::Admin => '管理者',
            self::User => 'ユーザー',
        };
    }
}
