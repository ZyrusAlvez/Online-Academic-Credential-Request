<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RequestDocumentPolicy
{
    /**
     * A representative's SPA and ID: the owner and registrars only (REQ-11-04).
     */
    public function view(User $user, RequestDocument $document): Response
    {
        return $user->hasRole(Role::Registrar) || $document->credentialRequest?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
