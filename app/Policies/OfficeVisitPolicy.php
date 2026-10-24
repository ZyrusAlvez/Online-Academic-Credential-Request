<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\RouteTask;
use App\Enums\VisitPurpose;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\User;

class OfficeVisitPolicy
{
    /**
     * Accept receipt, approve and forward, return, record a delay: staff of the visit's office,
     * or registrar accounts for the Office of the Registrar's visits (REQ-05-11).
     */
    public function act(User $user, OfficeVisit $visit): bool
    {
        if (! $this->isActionable($visit)) {
            return false;
        }

        if ($user->hasRole(Role::Department)) {
            return $user->department_id !== null && $user->department_id === $visit->department_id;
        }

        return $user->hasRole(Role::Registrar) && $visit->department?->code === Department::REGISTRAR_CODE;
    }

    /** Registrar override (REQ-05-10). */
    public function bypass(User $user, OfficeVisit $visit): bool
    {
        return $user->hasRole(Role::Registrar)
            && $this->isActionable($visit)
            && $visit->purpose === VisitPurpose::Route
            && $visit->step?->task !== RouteTask::FinalCheck;
    }

    private function isActionable(OfficeVisit $visit): bool
    {
        return $visit->status->isOpen()
            && in_array($visit->credentialRequest?->status, RequestStatus::processing(), true);
    }
}
