<?php

namespace App\Support;

use App\Enums\RegistrarQueue;
use App\Models\CredentialRequest;

/**
 * Where a registrar works on a request now: its page under the queue it is in, the In Progress
 * list searched by its tracking number (no working page there), or its Records page once it is
 * closed (REQ-16-26, REQ-17-13).
 */
final class RegistrarLink
{
    public static function to(CredentialRequest $request): string
    {
        $queue = RegistrarQueue::forRequest($request);

        return match (true) {
            $queue === null => route('records.show', $request),
            ! $queue->hasWorkingPage() => route($queue->routeName(), ['search' => $request->reference_no]),
            default => route($queue->showRouteName(), $request),
        };
    }
}
