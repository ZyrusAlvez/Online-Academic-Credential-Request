<?php

namespace App\Livewire\Forms;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Validation\Rule;
use Livewire\Form;

class DepartmentForm extends Form
{
    public ?Department $department = null;

    public string $name = '';

    public string $code = '';

    public string $type = 'office';

    public string $description = '';

    public function edit(Department $department): void
    {
        $this->department = $department;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->type = $department->type->value;
        $this->description = $department->description ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->ignore($this->department)],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('departments', 'code')->ignore($this->department)],
            'type' => ['required', Rule::enum(DepartmentType::class)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(): Department
    {
        $validated = $this->validate();
        $validated['description'] = filled($validated['description']) ? $validated['description'] : null;

        $department = $this->department ?? new Department;
        $department->fill($validated)->save();

        $this->reset();

        return $department;
    }
}
