<?php

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\User;
use DomainException;

/**
 * Creates or updates a department/registrar staff account on behalf of the registrar. The role is
 * set when the account is added; afterwards it changes only through ChangeAccountRole (REQ-02-15).
 */
class SaveStaffAccount
{
    /**
     * @param  array{first_name: string, middle_name: ?string, last_name: string, email: string, role: Role, department_id: ?int, password: ?string}  $data
     */
    public function handle(User $actor, array $data, ?User $account = null): User
    {
        if ($account?->role === Role::Student) {
            throw new DomainException('Student profiles are kept by the students themselves.');
        }

        $role = $account->role ?? $data['role'];

        if ($role === Role::Student) {
            throw new DomainException('Student accounts are self-registered.');
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
            'role' => $role,
            'department_id' => $role === Role::Department ? $data['department_id'] : null,
        ])->save();

        return $account;
    }
}
