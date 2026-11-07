<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\NonWorkingDay;
use App\Models\User;

/**
 * The working calendar is managed by the Admin (REQ-18-04).
 */
class NonWorkingDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, NonWorkingDay $day): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function delete(User $user, NonWorkingDay $day): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
