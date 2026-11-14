<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CredentialType;
use App\Models\User;

/**
 * The credential master list and routes are the Admin's (REQ-18-04).
 */
class CredentialTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /** Covers editing the type and its workflow. */
    public function update(User $user, CredentialType $credentialType): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
