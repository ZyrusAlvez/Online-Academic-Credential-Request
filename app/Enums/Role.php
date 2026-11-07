<?php

namespace App\Enums;

/**
 * The four account types (REQ-18-01).
 */
enum Role: string
{
    case Student = 'student';
    case Registrar = 'registrar';
    case Department = 'department';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Registrar => 'Registrar',
            self::Department => 'Department Staff',
            self::Admin => 'Admin',
        };
    }

    /**
     * Where `/dashboard` and logging in lead: requesters start on the new-request form, the
     * Registrar on Initial Evaluation, the Admin on the dashboard (REQ-18-02, 03).
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Student => 'student.requests.create',
            self::Registrar => 'registrar.evaluation',
            self::Department => 'department.dashboard',
            self::Admin => 'admin.dashboard',
        };
    }
}
