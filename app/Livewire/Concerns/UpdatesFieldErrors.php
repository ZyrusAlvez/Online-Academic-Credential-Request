<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

/**
 * When a field of a form object changes, check that form's fields that show an error again: each
 * one now valid loses its error, one still invalid keeps (or updates) it (REQ-01-31). Fields
 * without an error are left alone, so nothing is flagged before the requester asks for a review.
 * Used after TrimsInput, so the trimmed value is the one checked.
 */
trait UpdatesFieldErrors
{
    public function updatedUpdatesFieldErrors(string $name): void
    {
        $property = Str::before($name, '.');

        // Their rules depend on state set for each full check (whether a saved ID may be replaced,
        // REQ-15-04), so changing one only clears its error.
        $clearOnly = ['id_type', 'id_file'];

        if (! (($this->all()[$property] ?? null) instanceof Form)) {
            return;
        }

        foreach (array_keys($this->getErrorBag()->messages()) as $field) {
            if (! str_starts_with($field, $property.'.')) {
                continue;
            }

            if (in_array(Str::after($field, '.'), $clearOnly, true)) {
                if ($field === $name) {
                    $this->resetErrorBag($field);
                }

                continue;
            }

            try {
                $this->validateOnly($field);
            } catch (ValidationException $e) {
                $this->setErrorBag($e->validator->errors());
            }
        }
    }
}
