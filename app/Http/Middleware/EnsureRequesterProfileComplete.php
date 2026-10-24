<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requesters fill in their academic profile and upload a valid ID before requesting (REQ-01-18).
 */
class EnsureRequesterProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canRequestCredentials()) {
            return redirect()->route('student.profile')
                ->with('notify', 'Complete your profile and upload a valid ID before requesting a credential.');
        }

        return $next($request);
    }
}
