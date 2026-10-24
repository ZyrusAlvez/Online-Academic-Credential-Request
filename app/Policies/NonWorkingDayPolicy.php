<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\NonWorkingDay;
use App\Models\User;

class NonWorkingDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function update(User $user, NonWorkingDay $day): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function delete(User $user, NonWorkingDay $day): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
