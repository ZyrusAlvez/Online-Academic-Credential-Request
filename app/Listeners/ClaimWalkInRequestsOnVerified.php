<?php

namespace App\Listeners;

use App\Actions\Requests\ClaimWalkInRequests;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * A requester's email was verified by the code, at sign-up or after changing it: their walk-in
 * requests become theirs (REQ-26-07).
 */
class ClaimWalkInRequestsOnVerified
{
    public function __construct(private readonly ClaimWalkInRequests $claim) {}

    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->claim->handle($event->user);
        }
    }
}
