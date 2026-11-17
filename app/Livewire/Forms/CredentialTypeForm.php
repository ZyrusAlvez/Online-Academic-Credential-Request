<?php

namespace App\Livewire\Forms;

use App\Enums\AcademicStatus;
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

    /** One requirement per line. */
    public string $requirements = '';

    public string $special_conditions = '';

    public bool $requires_receiving_institution = false;

    /** Per-type options (REQ-29-03). */
    public bool $asks_term_and_year = false;

    public bool $fee_per_term = false;

    /** @var list<string> AcademicStatus values */
    public array $free_for = [];

    public function edit(CredentialType $credentialType): void
    {
        $this->credentialType = $credentialType;
        $this->name = $credentialType->name;
        $this->description = $credentialType->description ?? '';
        $this->requires_payment = $credentialType->requires_payment;
        $this->fee = $credentialType->fee ?? '';
        $this->processing_days = $credentialType->processing_days;
        $this->requirements = implode("\n", $credentialType->requirements ?? []);
        $this->special_conditions = $credentialType->special_conditions ?? '';
        $this->requires_receiving_institution = $credentialType->requires_receiving_institution;
        $this->asks_term_and_year = $credentialType->asks_term_and_year;
        $this->fee_per_term = $credentialType->fee_per_term;
        $this->free_for = $credentialType->free_for ?? [];
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
            'requirements' => ['nullable', 'string', 'max:3000'],
            'special_conditions' => ['nullable', 'string', 'max:1000'],
            'requires_receiving_institution' => ['boolean'],
            'asks_term_and_year' => ['boolean'],
            'fee_per_term' => ['boolean'],
            'free_for' => ['array'],
            'free_for.*' => [Rule::enum(AcademicStatus::class)],
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
            'requirements' => $this->requirementList((string) $validated['requirements']) ?: null,
            'special_conditions' => filled($validated['special_conditions']) ? $validated['special_conditions'] : null,
            'requires_receiving_institution' => $validated['requires_receiving_institution'],
            'asks_term_and_year' => $validated['asks_term_and_year'],
            // Fee options mean nothing without a fee to pay (REQ-29-03).
            'fee_per_term' => $validated['requires_payment'] && $validated['fee_per_term'],
            'free_for' => $validated['requires_payment'] && $validated['free_for'] !== [] ? array_values(array_unique($validated['free_for'])) : null,
        ])->save();

        $this->reset();

        return $credentialType;
    }

    /**
     * @return list<string>
     */
    private function requirementList(string $text): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/\R/', $text) ?: [])));
    }
}
