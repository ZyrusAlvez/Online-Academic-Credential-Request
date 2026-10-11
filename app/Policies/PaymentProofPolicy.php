<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentProofPolicy
{
    public function view(User $user, PaymentProof $proof): Response
    {
        return $user->hasRole(Role::Registrar) || $proof->credentialRequest?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
