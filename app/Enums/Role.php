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

    /**
     * Where `/dashboard` and logging in lead (REQ-01-10): requesters start on the new-request form.
     */
    public function homeRoute(): string
    {
        return $this === self::Student ? 'student.requests.create' : $this->value.'.dashboard';
    }
}
