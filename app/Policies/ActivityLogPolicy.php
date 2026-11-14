<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class ActivityLogPolicy
{
    /** The Logs page (REQ-14-13), the Admin's (REQ-18-04). */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
