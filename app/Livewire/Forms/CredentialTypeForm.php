<?php

namespace App\Livewire\Forms;

use App\Models\CredentialType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CredentialTypeForm extends Form
{
    public ?CredentialType $credentialType = null;

    public string $name = '';

    public string $description = '';

    public bool $requires_payment = false;

    public string $fee = '';

    public int|string|null $processing_days = 5;

    public function edit(CredentialType $credentialType): void
    {
        $this->credentialType = $credentialType;
        $this->name = $credentialType->name;
        $this->description = $credentialType->description ?? '';
        $this->requires_payment = $credentialType->requires_payment;
        $this->fee = $credentialType->fee ?? '';
        $this->processing_days = $credentialType->processing_days;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('credential_types', 'name')->ignore($this->credentialType)],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_payment' => ['boolean'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'processing_days' => ['required', 'integer', 'between:1,60'],
        ];
    }

    public function save(): CredentialType
    {
        $validated = $this->validate();

        $credentialType = $this->credentialType ?? new CredentialType;
        $credentialType->fill([
            'name' => $validated['name'],
            'description' => filled($validated['description']) ? $validated['description'] : null,
            'requires_payment' => $validated['requires_payment'],
            'fee' => $validated['requires_payment'] && filled($validated['fee']) ? $validated['fee'] : null,
            'processing_days' => (int) $validated['processing_days'],
        ])->save();

        $this->reset();

        return $credentialType;
    }
}
