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
     * Owners see their own requests; offices see requests that have been sent to them.
     * Anyone else gets a 404 so tracking numbers cannot be probed.
     */
    public function view(User $user, CredentialRequest $request): Response
    {
        $allowed = match ($user->role) {
            Role::Registrar => true,
            Role::Student => $request->user_id === $user->id,
            Role::Department => $user->department_id !== null
                && $request->visits()->where('department_id', $user->department_id)->exists(),
        };

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function uploadPayment(User $user, CredentialRequest $request): bool
    {
        return $request->user_id === $user->id && $request->status === RequestStatus::AwaitingPayment;
    }

    /** Reply to the registrar's request for action. */
    public function respond(User $user, CredentialRequest $request): bool
    {
        return $request->user_id === $user->id && $request->status === RequestStatus::ReturnedForCustomerAction;
    }

    public function declareRepresentative(User $user, CredentialRequest $request): bool
    {
        if ($request->status->isFinal()) {
            return false;
        }

        return $user->hasRole(Role::Registrar) || $request->user_id === $user->id;
    }

    public function cancel(User $user, CredentialRequest $request): bool
    {
        if ($user->hasRole(Role::Registrar)) {
            return ! $request->status->isFinal();
        }

        return $request->user_id === $user->id
            && $request->accepted_at === null
            && ($request->status->isBeforeAcceptance() || $request->status === RequestStatus::ReturnedForCustomerAction);
    }

    public function release(User $user, CredentialRequest $request): bool
    {
        return $user->hasRole(Role::Registrar) && $request->status === RequestStatus::ReadyForRelease;
    }

    /** Evaluate, verify payment, accept, correct the college, pause, record delays. */
    public function review(User $user, CredentialRequest $request): bool
    {
        return $user->hasRole(Role::Registrar);
    }
}
