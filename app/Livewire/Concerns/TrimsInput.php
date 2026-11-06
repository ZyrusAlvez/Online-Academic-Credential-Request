<?php

namespace App\Livewire\Concerns;

/**
 * Trims leading/trailing whitespace from text input as it arrives. Livewire's update
 * requests skip Laravel's TrimStrings middleware, so components opt in here.
 * Passwords are left exactly as typed, and so is a live search box: trimming it would send the
 * value back and eat the space between words as the user pauses (REQ-09-17), so the search
 * query trims it instead.
 */
trait TrimsInput
{
    public function updatedTrimsInput(string $name, mixed $value): void
    {
        if (! is_string($value) || $name === 'search' || str_contains(strtolower($name), 'password') || $value === trim($value)) {
            return;
        }

        data_set($this, $name, trim($value));
    }
}
