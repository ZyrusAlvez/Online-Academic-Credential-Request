<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentProofPolicy
{
    /** The owner, registrars and admins (REQ-18-07). */
    public function view(User $user, PaymentProof $proof): Response
    {
        return $user->hasRole(Role::Registrar, Role::Admin) || $proof->credentialRequest?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
