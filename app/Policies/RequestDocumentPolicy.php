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
     * REQ-18-07). A deleted request's: the Admin only (REQ-37-02).
     */
    public function view(User $user, RequestDocument $document): Response
    {
        $request = $document->credentialRequest;

        $allowed = $user->hasRole(Role::Admin)
            || ($request !== null && ($user->hasRole(Role::Registrar) || $request->user_id === $user->id));

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
