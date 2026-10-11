<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InvalidTransition;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Runs a RequestWorkflow transition and reports the outcome as a toast. Refused transitions
 * (e.g. someone else already signed) are shown to the user instead of raising an error page.
 */
trait RunsTransitions
{
    protected function attempt(Closure $transition, string $success): bool
    {
        try {
            $transition();
        } catch (InvalidTransition $e) {
            $this->dispatch('notify', message: $e->getMessage());

            return false;
        }

        $this->dispatch('notify', message: $success);

        return true;
    }

    protected function actor(): User
    {
        /** @var User */
        return Auth::user();
    }
}
