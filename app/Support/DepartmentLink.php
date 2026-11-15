<?php

namespace App\Support;

use App\Enums\DepartmentQueue;
use App\Models\CredentialRequest;

/**
 * Where an office works on a request now: its page under the office's list it is in (REQ-24-06).
 */
final class DepartmentLink
{
    public static function to(CredentialRequest $request, int $departmentId): string
    {
        return route(DepartmentQueue::forRequest($request, $departmentId)->showRouteName(), $request);
    }
}
