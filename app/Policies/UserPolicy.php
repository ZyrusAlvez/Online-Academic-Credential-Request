<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Account management by the registrar.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    /** Only staff accounts are editable; students manage their own profile. */
    public function update(User $user, User $account): bool
    {
        return $user->hasRole(Role::Registrar) && ! $account->hasRole(Role::Student);
    }

    public function setStatus(User $user, User $account): bool
    {
        return $user->hasRole(Role::Registrar) && ! $account->is($user);
    }
}
