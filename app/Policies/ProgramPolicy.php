<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
