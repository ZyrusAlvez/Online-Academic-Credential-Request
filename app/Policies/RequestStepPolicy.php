<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\RequestStep;
use App\Models\User;

class RequestStepPolicy
{
    /**
     * Sign, hold or lift a hold on the current step: its own department's staff, or registrar
     * accounts for the Office of the Registrar's steps.
     */
    public function act(User $user, RequestStep $step): bool
    {
        if (! $this->isActionable($step)) {
            return false;
        }

        if ($user->hasRole(Role::Department)) {
            return $user->department_id !== null && $user->department_id === $step->department_id;
        }

        return $user->hasRole(Role::Registrar) && $step->department?->code === Department::REGISTRAR_CODE;
    }

    public function skip(User $user, RequestStep $step): bool
    {
        return $user->hasRole(Role::Registrar) && $this->isActionable($step);
    }

    private function isActionable(RequestStep $step): bool
    {
        return $step->status->isCurrent()
            && $step->credentialRequest?->status === RequestStatus::InProgress;
    }
}
