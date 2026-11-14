<?php

namespace App\Actions\Staff;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use DomainException;
use Illuminate\Validation\ValidationException;

/**
 * Moves an existing account to another role (REQ-02-15). The account keeps its name, email,
 * password, requester profile and history; the role middleware applies the change on its next page.
 */
class ChangeAccountRole
{
    public function handle(User $actor, User $account, Role $role, ?int $departmentId = null): User
    {
        if ($account->is($actor)) {
            throw new DomainException('You cannot change your own role.');
        }

        if ($role === Role::Department && $departmentId === null) {
            throw new DomainException('Department staff need a department.');
        }

        // Only a student can pay, comply with or pick up their requests (REQ-02-16).
        if ($account->hasRole(Role::Student) && $role !== Role::Student && $this->hasOpenRequests($account)) {
            throw ValidationException::withMessages([
                'role' => "{$account->name} still has open requests. They must be released, declined or cancelled before the role changes.",
            ]);
        }

        $account->forceFill([
            'role' => $role,
            'department_id' => $role === Role::Department ? $departmentId : null,
        ])->save();

        return $account;
    }

    private function hasOpenRequests(User $account): bool
    {
        return CredentialRequest::query()
            ->where('user_id', $account->id)
            ->whereIn('status', RequestStatus::open())
            ->exists();
    }
}
