<?php

namespace App\Enums;

/**
 * How a request was made: online by the requester, or recorded by the Registrar for a walk-in
 * client (REQ-25-04, 07; spec 26). The Cashier validates online payments, the Registrar walk-in
 * ones. Stored as `request_type` (REQ-26-10; "channel" before 2026-11-19).
 */
enum RequestType: string
{
    case Online = 'online';
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::WalkIn => 'Walk-in',
        };
    }

    /** As Request Details shows it (REQ-26-10). */
    public function detailLabel(): string
    {
        return match ($this) {
            self::Online => 'Online request',
            self::WalkIn => 'Walk-in request',
        };
    }
}
