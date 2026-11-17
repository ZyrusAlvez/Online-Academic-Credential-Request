<?php

namespace App\Enums;

/**
 * A term of the school year, asked by credentials such as the Semestral Report of Grades (REQ-29-04).
 */
enum Term: string
{
    case First = 'first';
    case Second = 'second';
    case Summer = 'summer';

    public function label(): string
    {
        return match ($this) {
            self::First => '1st Semester',
            self::Second => '2nd Semester',
            self::Summer => 'Summer',
        };
    }

    /**
     * The labels of the given term values, in the school year's order, e.g. "1st Semester, Summer".
     *
     * @param  ?list<string>  $values
     */
    public static function labels(?array $values): string
    {
        return collect(self::cases())
            ->filter(fn (self $term): bool => in_array($term->value, $values ?? [], true))
            ->map(fn (self $term): string => $term->label())
            ->implode(', ');
    }
}
