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
     * The owner, registrars and admins (REQ-18-07); cashiers for online requests (REQ-25-06);
     * department staff only the verified proof of a request sent to their office (REQ-24-08).
     * Anyone else gets a 404; a deleted request's proofs are the Admin's only (REQ-37-02).
     */
    public function view(User $user, PaymentProof $proof): Response
    {
        $request = $proof->credentialRequest;

        $allowed = match (true) {
            $user->hasRole(Role::Admin) => true,
            $request === null => false,
            $user->hasRole(Role::Registrar) => true,
            $request->user_id === $user->id => true,
            // The proofs of the online requests whose payment the cashier validates (REQ-25-06).
            $user->hasRole(Role::Cashier) => ! $request->isWalkIn(),
            $user->hasRole(Role::Department) => $user->department_id !== null
                && $proof->status === PaymentStatus::Verified
                && $request->visits()->where('department_id', $user->department_id)->exists(),
            default => false,
        };

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
