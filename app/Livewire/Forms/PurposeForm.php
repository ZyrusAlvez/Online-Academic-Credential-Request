<?php

namespace App\Livewire\Forms;

use App\Models\Purpose;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PurposeForm extends Form
{
    public ?Purpose $purpose = null;

    public string $name = '';

    public function edit(Purpose $purpose): void
    {
        $this->purpose = $purpose;
        $this->name = $purpose->name;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('purposes', 'name')->ignore($this->purpose)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.unique' => 'There is already a purpose with that name.',
        ];
    }

    public function save(): Purpose
    {
        $validated = $this->validate();

        $purpose = $this->purpose ?? new Purpose;
        $purpose->fill(['name' => $validated['name']])->save();

        $this->reset();

        return $purpose;
    }
}
