<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;

/**
 * Offices are managed by the Admin (REQ-18-04).
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
