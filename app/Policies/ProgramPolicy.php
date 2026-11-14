<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Program;
use App\Models\User;

/**
 * Programs and strands are managed by the Admin (REQ-18-04).
 */
class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
