<?php

namespace App\Enums;

/**
 * What an office does with the credential at its route step (spec 03).
 */
enum RouteTask: string
{
    case Prepare = 'prepare';
    case Sign = 'sign';
    case DocumentaryStamp = 'documentary_stamp';

    public function label(): string
    {
        return match ($this) {
            self::Prepare => 'Prepare',
            self::Sign => 'Sign',
            self::DocumentaryStamp => 'Documentary stamp',
        };
    }

    public function doneLabel(): string
    {
        return match ($this) {
            self::Prepare => 'Prepared',
            self::Sign => 'Signed',
            self::DocumentaryStamp => 'Stamped',
        };
    }
}
