<?php

namespace App\Livewire\Forms;

use App\Actions\Staff\SaveStaffAccount;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Form;

class StaffForm extends Form
{
    public ?User $account = null;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $role = 'department';

    public ?int $department_id = null;

    public string $password = '';

    public function edit(User $account): void
    {
        $this->account = $account;
        $this->first_name = $account->first_name;
        $this->middle_name = $account->middle_name ?? '';
        $this->last_name = $account->last_name;
        $this->email = $account->email;
        $this->role = $account->role->value;
        $this->department_id = $account->department_id;
        $this->password = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->account)],
            'role' => ['required', Rule::in([Role::Department->value, Role::Registrar->value])],
            'department_id' => [
                Rule::requiredIf($this->role === Role::Department->value),
                'nullable',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'password' => [$this->account ? 'nullable' : 'required', 'string', Password::defaults()],
        ];
    }

    public function save(User $actor): User
    {
        $validated = $this->validate();

        $account = app(SaveStaffAccount::class)->handle($actor, [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'role' => Role::from($validated['role']),
            'department_id' => $validated['department_id'] ?? null,
            'password' => $validated['password'] ?? null,
        ], $this->account);

        $this->reset();

        return $account;
    }
}
