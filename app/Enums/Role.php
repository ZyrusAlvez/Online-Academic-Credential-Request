<?php

namespace App\Enums;

/**
 * The five account types (REQ-18-01, REQ-25-01).
 */
enum Role: string
{
    case Student = 'student';
    case Registrar = 'registrar';
    case Department = 'department';
    case Admin = 'admin';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Registrar => 'Registrar',
            self::Department => 'Department Staff',
            self::Admin => 'Admin',
            self::Cashier => 'Cashier',
        };
    }

    /**
     * Where `/dashboard` and logging in lead: requesters start on the new-request form, the
     * Registrar on Initial Evaluation, the Admin on the dashboard, the Cashier on Payment
     * Validation (REQ-18-02, 03, REQ-25-01).
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Student => 'student.requests.create',
            self::Registrar => 'registrar.evaluation',
            self::Department => 'department.incoming',
            self::Admin => 'admin.dashboard',
            self::Cashier => 'cashier.payments',
        };
    }
}
