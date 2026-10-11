<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CredentialType;
use App\Models\User;

class CredentialTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    /** Covers editing the type and its workflow. */
    public function update(User $user, CredentialType $credentialType): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
