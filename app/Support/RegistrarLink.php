<?php

namespace App\Support;

use App\Enums\RegistrarQueue;
use App\Models\CredentialRequest;

/**
 * Where a registrar works on a request now: its page under the queue it is in, or its Records
 * page once it is closed (REQ-16-26).
 */
final class RegistrarLink
{
    public static function to(CredentialRequest $request): string
    {
        $queue = RegistrarQueue::forRequest($request);

        return $queue !== null ? route($queue->showRouteName(), $request) : route('records.show', $request);
    }
}
