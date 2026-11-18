<?php

namespace App\Support;

use App\Enums\RegistrarQueue;
use App\Models\CredentialRequest;

/**
 * Where a registrar works on a request now: its page under the queue it is in, the Outgoing list
 * searched by its tracking number (no working page there), or its Records page once it is closed
 * or while its paper is at an office (REQ-16-26, REQ-32-10).
 */
final class RegistrarLink
{
    public static function to(CredentialRequest $request): string
    {
        // Until acceptance the application is worked on through its first credential (REQ-27-03).
        if (! $request->isLead() && $request->status->isBeforeAcceptance()) {
            $request = $request->application()->firstOrFail()->lead()->firstOrFail();
        }

        $queue = RegistrarQueue::forRequest($request);

        return match (true) {
            $queue === null => route('records.show', $request),
            ! $queue->hasWorkingPage() => route($queue->routeName(), ['search' => $request->reference_no]),
            default => route($queue->showRouteName(), $request),
        };
    }
}
