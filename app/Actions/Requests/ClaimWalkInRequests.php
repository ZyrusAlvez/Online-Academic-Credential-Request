<?php

namespace App\Actions\Requests;

use App\Enums\RequestType;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Support\Str;

/**
 * Link a requester's walk-in requests to their account once its email is verified, so they can
 * track them (REQ-26-07). Runs on email verification and on every Google sign-in.
 */
class ClaimWalkInRequests
{
    public function __construct(private readonly RequestWorkflow $workflow) {}

    /**
     * @return int how many requests were linked
     */
    public function handle(User $user): int
    {
        if (! $user->hasRole(Role::Student) || ! $user->hasVerifiedEmail()) {
            return 0;
        }

        $requests = CredentialRequest::query()
            ->where('request_type', RequestType::WalkIn)
            ->whereNull('user_id')
            ->where('requester_email', Str::lower($user->email))
            ->orderBy('id')
            ->get();

        foreach ($requests as $request) {
            $this->workflow->linkToAccount($request, $user);
        }

        if ($requests->isNotEmpty()) {
            $this->fillProfile($user, $requests->last());
        }

        return $requests->count();
    }

    /**
     * An account with no contact number, sex or academic information yet takes them from the newest
     * linked request; anything the requester already gave stays (REQ-26-07, REQ-28-06).
     */
    private function fillProfile(User $user, CredentialRequest $request): void
    {
        if (blank($user->contact_number)) {
            $user->contact_number = $request->contact_number;
        }

        if ($user->sex === null) {
            $user->forceFill(['sex' => $request->sex]);
        }

        if ($user->college_id === null) {
            $user->forceFill([
                'academic_status' => $request->academic_status,
                'college_id' => $request->college_id,
                'program' => $request->program,
                'student_number' => $request->student_number,
                'attended_from' => $request->attended_from,
                'attended_to' => $request->attended_to,
            ]);
        }

        $user->save();
    }
}
