<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IdentityDocumentPolicy
{
    /**
     * Only the owner and registrars may open an ID; anyone else sees nothing (REQ-01-20).
     */
    public function view(User $user, IdentityDocument $document): Response
    {
        return $user->hasRole(Role::Registrar) || $document->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function review(User $user, IdentityDocument $document): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
