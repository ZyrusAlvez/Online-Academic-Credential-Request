<?php

namespace App\Enums;

/**
 * Kinds of entries in the system-wide activity log (spec 14).
 */
enum ActivityCategory: string
{
    case Request = 'request';
    case Account = 'account';
    case Setup = 'setup';
    case Identity = 'identity';
    case SignIn = 'sign_in';

    public function label(): string
    {
        return match ($this) {
            self::Request => 'Requests',
            self::Account => 'Accounts',
            self::Setup => 'Setup',
            self::Identity => 'Identity checks',
            self::SignIn => 'Sign-ins',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Request => 'blue',
            self::Account => 'violet',
            self::Setup => 'amber',
            self::Identity => 'teal',
            self::SignIn => 'zinc',
        };
    }
}
