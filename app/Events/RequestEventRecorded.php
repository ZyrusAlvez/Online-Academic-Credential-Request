<?php

namespace App\Events;

use App\Models\RequestEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the transaction that appended a ledger entry has committed.
 */
class RequestEventRecorded
{
    use Dispatchable;

    public function __construct(public RequestEvent $event) {}
}
