<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Registrar = 'registrar';
    case Department = 'department';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Registrar => 'Registrar',
            self::Department => 'Department Staff',
        };
    }

    public function homeRoute(): string
    {
        return $this->value.'.dashboard';
    }
}
