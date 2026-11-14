<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Account management by the Admin (REQ-18-04, 09, 10).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /** Only staff accounts are editable; students manage their own profile. */
    public function update(User $user, User $account): bool
    {
        return $user->hasRole(Role::Admin) && ! $account->hasRole(Role::Student);
    }

    /** Never one's own, so an Admin always remains (REQ-18-10). */
    public function setStatus(User $user, User $account): bool
    {
        return $user->hasRole(Role::Admin) && ! $account->is($user);
    }

    /** Any account's role, students included, but never one's own (REQ-02-15, REQ-18-10). */
    public function changeRole(User $user, User $account): bool
    {
        return $user->hasRole(Role::Admin) && ! $account->is($user);
    }
}
