<?php

namespace App\Livewire\Forms;

use App\Actions\Staff\ChangeAccountRole;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

/**
 * The Change role dialog on the Accounts page (REQ-02-15).
 */
class RoleForm extends Form
{
    public ?User $account = null;

    public string $role = '';

    public int|string|null $department_id = null;

    public function edit(User $account): void
    {
        $this->account = $account;
        $this->role = $account->role->value;
        $this->department_id = $account->department_id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
            // A department picked before switching to another role is ignored.
            'department_id' => [
                Rule::excludeIf($this->role !== Role::Department->value),
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * The account with its new role, or null when nothing changed (the error says why).
     */
    public function save(User $actor): ?User
    {
        $validated = $this->validate();
        $role = Role::from($validated['role']);
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;

        /** @var User $account */
        $account = $this->account;

        if ($role === $account->role && $departmentId === $account->department_id) {
            $this->addError('role', __('Choose a different role or department.'));

            return null;
        }

        try {
            app(ChangeAccountRole::class)->handle($actor, $account, $role, $departmentId);
        } catch (ValidationException $e) {
            $this->addError('role', $e->validator->errors()->first());

            return null;
        }

        $this->reset();

        return $account;
    }
}
