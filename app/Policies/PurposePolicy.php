<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Purpose;
use App\Models\User;

class PurposePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function update(User $user, Purpose $purpose): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
