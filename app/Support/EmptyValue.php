<?php

namespace App\Support;

use Stringable;

/**
 * Text that stands in for a missing value on a summary, e.g. "Not provided" (REQ-15-19).
 * <x-summary-section> shows it in grey italics, so it is never mistaken for something the
 * requester typed (someone could type "Not provided" themselves).
 */
final class EmptyValue implements Stringable
{
    public function __construct(public readonly string $text) {}

    /** "Not provided": an optional field left blank. */
    public static function notProvided(): self
    {
        return new self(__('Not provided'));
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
