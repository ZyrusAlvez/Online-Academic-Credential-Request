<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentProofPolicy
{
    /**
     * The owner, registrars and admins (REQ-18-07); department staff only the verified proof of
     * a request sent to their office (REQ-24-08). Anyone else gets a 404.
     */
    public function view(User $user, PaymentProof $proof): Response
    {
        $request = $proof->credentialRequest;

        $allowed = match (true) {
            $user->hasRole(Role::Registrar, Role::Admin) => true,
            $request === null => false,
            $request->user_id === $user->id => true,
            $user->hasRole(Role::Department) => $user->department_id !== null
                && $proof->status === PaymentStatus::Verified
                && $request->visits()->where('department_id', $user->department_id)->exists(),
            default => false,
        };

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
