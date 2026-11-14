<?php

namespace App\Livewire\Forms;

use App\Enums\DepartmentType;
use App\Models\Program;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProgramForm extends Form
{
    public ?Program $program = null;

    public string $name = '';

    public int|string|null $department_id = null;

    public function edit(Program $program): void
    {
        $this->program = $program;
        $this->name = $program->name;
        $this->department_id = $program->department_id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('type', DepartmentType::College->value)],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('programs', 'name')->where('department_id', (int) $this->department_id)->ignore($this->program),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'department_id' => 'department',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.unique' => 'This department already has a program with that name.',
        ];
    }

    public function save(): Program
    {
        $validated = $this->validate();

        $program = $this->program ?? new Program;
        $program->fill(['name' => $validated['name'], 'department_id' => (int) $validated['department_id']])->save();

        $this->reset();

        return $program;
    }
}
