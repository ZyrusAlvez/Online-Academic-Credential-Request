<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Purpose;
use App\Models\User;

/**
 * Request purposes are managed by the Admin (REQ-18-04).
 */
class PurposePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, Purpose $purpose): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
