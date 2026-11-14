<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RequestDocumentPolicy
{
    /**
     * A representative's SPA and ID: the owner, registrars and admins only (REQ-11-04,
     * REQ-18-07).
     */
    public function view(User $user, RequestDocument $document): Response
    {
        return $user->hasRole(Role::Registrar, Role::Admin) || $document->credentialRequest?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
