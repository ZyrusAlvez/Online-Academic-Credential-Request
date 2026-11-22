<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The registrar accounts to tell about a request (REQ-06-21): its evaluator while that account is
 * active, otherwise every active registrar, so nothing goes unheard.
 */
class RequestRegistrars
{
    /**
     * @return Collection<int, User>
     */
    public static function for(CredentialRequest $request): Collection
    {
        if ($request->evaluated_by !== null) {
            $evaluator = User::query()
                ->whereKey($request->evaluated_by)
                ->where('role', Role::Registrar)
                ->where('is_active', true)
                ->get();

            if ($evaluator->isNotEmpty()) {
                return $evaluator;
            }
        }

        return self::all();
    }

    /**
     * @return Collection<int, User>
     */
    public static function all(): Collection
    {
        return User::query()->where('role', Role::Registrar)->where('is_active', true)->get();
    }
}
