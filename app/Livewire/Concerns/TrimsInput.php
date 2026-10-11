<?php

namespace App\Livewire\Concerns;

/**
 * Trims leading/trailing whitespace from text input as it arrives. Livewire's update
 * requests skip Laravel's TrimStrings middleware, so components opt in here.
 * Passwords are left exactly as typed.
 */
trait TrimsInput
{
    public function updatedTrimsInput(string $name, mixed $value): void
    {
        if (! is_string($value) || str_contains(strtolower($name), 'password') || $value === trim($value)) {
            return;
        }

        data_set($this, $name, trim($value));
    }
}
