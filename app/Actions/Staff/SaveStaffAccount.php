<?php

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\User;
use DomainException;

/**
 * Creates or updates a department/registrar staff account on behalf of the registrar.
 */
class SaveStaffAccount
{
    /**
     * @param  array{first_name: string, middle_name: ?string, last_name: string, email: string, role: Role, department_id: ?int, password: ?string}  $data
     */
    public function handle(User $actor, array $data, ?User $account = null): User
    {
        if ($data['role'] === Role::Student) {
            throw new DomainException('Student accounts are self-registered.');
        }

        if ($account?->role === Role::Student) {
            throw new DomainException('Student accounts cannot be converted to staff.');
        }

        if ($account?->is($actor) && $data['role'] !== $actor->role) {
            throw new DomainException('You cannot change your own role.');
        }

        $account ??= new User;

        $account->fill([
            'first_name' => $data['first_name'],
            'middle_name' => filled($data['middle_name']) ? $data['middle_name'] : null,
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ]);

        if (filled($data['password'])) {
            $account->password = $data['password'];
        }

        if ($account->isDirty('email')) {
            // Staff emails are vouched for by the registrar.
            $account->email_verified_at = now();
        }

        $account->forceFill([
            'role' => $data['role'],
            'department_id' => $data['role'] === Role::Department ? $data['department_id'] : null,
        ])->save();

        return $account;
    }
}
