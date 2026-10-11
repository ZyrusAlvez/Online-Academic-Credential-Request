<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CredentialRequestPolicy
{
    /** The registrar's list of all requests. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Student);
    }

    /**
     * Students see their own requests; departments see requests routed through them.
     * Anyone else gets a 404 so request references cannot be probed.
     */
    public function view(User $user, CredentialRequest $request): Response
    {
        $allowed = match ($user->role) {
            Role::Registrar => true,
            Role::Student => $request->user_id === $user->id,
            Role::Department => $user->department_id !== null
                && $request->steps()->where('department_id', $user->department_id)->exists(),
        };

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function uploadPayment(User $user, CredentialRequest $request): bool
    {
        return $request->user_id === $user->id && $request->status === RequestStatus::AwaitingPayment;
    }

    public function cancel(User $user, CredentialRequest $request): bool
    {
        if ($user->hasRole(Role::Registrar)) {
            return ! $request->status->isFinal();
        }

        return $request->user_id === $user->id && $request->status->isBeforeRouting();
    }

    /** Approve, reject payment, correct college. */
    public function review(User $user, CredentialRequest $request): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
