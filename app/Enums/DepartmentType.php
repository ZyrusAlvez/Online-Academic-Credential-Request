<?php

namespace App\Enums;

enum DepartmentType: string
{
    case College = 'college';
    case Office = 'office';

    public function label(): string
    {
        return match ($this) {
            self::College => 'College',
            self::Office => 'Office',
        };
    }
}
