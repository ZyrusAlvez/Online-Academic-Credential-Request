<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CredentialRequestPolicy
{
    /**
     * Every request: Records for the Registrar and the Admin, the Admin's dashboard and reports
     * (REQ-18-06, 08). The stage queues are the Registrar's by their route (REQ-18-05).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Registrar, Role::Admin);
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
            Role::Registrar, Role::Admin => true,
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

    /**
     * Edit and resubmit a request returned for clarification; nothing else is editable
     * (REQ-20-05, 13).
     */
    public function edit(User $user, CredentialRequest $request): Response
    {
        if ($request->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $request->status === RequestStatus::ReturnedForClarification
            ? Response::allow()
            : Response::deny('Only a request returned to you for clarification can be edited.');
    }

    /**
     * After submitting, the owner adds a representative only when they may send someone in their
     * place: to an office visit the evaluator requires (Proposed Workflow III-C), or to claim the
     * credential once it is ready (VIII-B) (REQ-11-01). New Request declares one at submission
     * through the workflow itself.
     */
    public function declareRepresentative(User $user, CredentialRequest $request): bool
    {
        if ($request->status->isFinal()) {
            return false;
        }

        if ($user->hasRole(Role::Registrar)) {
            return true;
        }

        return $request->user_id === $user->id
            && in_array($request->status, [RequestStatus::PendingCompliance, RequestStatus::ReadyForRelease], true);
    }

    /** Only the owner, before acceptance (REQ-04-16, REQ-16-21). */
    public function cancel(User $user, CredentialRequest $request): bool
    {
        return $request->user_id === $user->id
            && $request->accepted_at === null
            && ($request->status->isBeforeAcceptance() || $request->status === RequestStatus::ReturnedForCustomerAction);
    }

    public function release(User $user, CredentialRequest $request): bool
    {
        return $user->hasRole(Role::Registrar) && $request->status === RequestStatus::ReadyForRelease;
    }

    /** Act on the Registrar's stages: evaluate, validate payment, final check, release. */
    public function review(User $user, CredentialRequest $request): bool
    {
        return $user->hasRole(Role::Registrar);
    }

    /**
     * Open the working page and act on it: not while another registrar is evaluating it
     * (REQ-16-09).
     */
    public function evaluate(User $user, CredentialRequest $request): Response
    {
        if (! $user->hasRole(Role::Registrar)) {
            return Response::deny();
        }

        return $request->isEvaluatedBySomeoneElse($user)
            ? Response::deny("{$request->reference_no} is under evaluation by {$request->evaluator()->first()?->name}.")
            : Response::allow();
    }
}
