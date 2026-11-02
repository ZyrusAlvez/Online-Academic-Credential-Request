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

    /** The Registrar's final quality check, appended to every request's route (spec 05). */
    case FinalCheck = 'final_check';

    public function label(): string
    {
        return match ($this) {
            self::Prepare => 'Prepare',
            self::Sign => 'Sign',
            self::DocumentaryStamp => 'Documentary stamp',
            self::FinalCheck => 'Final quality check',
        };
    }

    /**
     * Tasks the registrar can give a route step; the final check is always added automatically.
     *
     * @return list<self>
     */
    public static function configurable(): array
    {
        return [self::Prepare, self::Sign, self::DocumentaryStamp];
    }

    public function doneLabel(): string
    {
        return match ($this) {
            self::Prepare => 'Prepared',
            self::Sign => 'Signed',
            self::DocumentaryStamp => 'Stamped',
            self::FinalCheck => 'Checked',
        };
    }
}
