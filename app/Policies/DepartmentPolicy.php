<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
