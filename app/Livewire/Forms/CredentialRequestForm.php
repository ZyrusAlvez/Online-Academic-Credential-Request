<?php

namespace App\Livewire\Forms;

use App\Enums\RequesterMode;
use App\Models\CredentialType;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * A new credential request (REQ-04-01). Academic details come from the requester's profile; the
 * purpose is one of the registrar's active purposes or, under "Others (please specify)", typed in
 * (spec 13).
 */
class CredentialRequestForm extends Form
{
    /** The dropdown value of "Others (please specify)" (REQ-13-11); not a stored purpose. */
    public const OTHER_PURPOSE = '__other__';

    // Numeric inputs accept strings so invalid input reaches validation instead of a TypeError.
    public int|string|null $credential_type_id = null;

    public int|string|null $copies = 1;

    public string $purpose = '';

    public string $purpose_other = '';

    public string $receiving_institution = '';

    public string $requester_mode = 'personal';

    public string $representative_name = '';

    public string $representative_relationship = '';

    public string $representative_contact = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $representative = $this->requester_mode === RequesterMode::Representative->value;
        $other = $this->purpose === self::OTHER_PURPOSE;

        return [
            'credential_type_id' => ['required', 'integer', Rule::in(CredentialType::requestable()->pluck('id')->all())],
            'copies' => ['required', 'integer', 'between:1,10'],
            'purpose' => $other ? ['required', 'string'] : ['required', 'string', Rule::exists('purposes', 'name')->where('is_active', true)],
            'purpose_other' => [Rule::requiredIf($other), 'nullable', 'string', 'max:500'],
            'receiving_institution' => [Rule::requiredIf(fn (): bool => (bool) $this->selectedType()?->requires_receiving_institution), 'nullable', 'string', 'max:255'],
            'requester_mode' => ['required', Rule::enum(RequesterMode::class)],
            'representative_name' => [Rule::requiredIf($representative), 'nullable', 'string', 'max:150'],
            'representative_relationship' => ['nullable', 'string', 'max:100'],
            'representative_contact' => ['nullable', 'string', 'max:20', RequesterProfileForm::PHONE_RULE],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'credential_type_id' => 'credential',
            'purpose_other' => 'purpose',
            'receiving_institution' => 'receiving school',
            'representative_name' => "representative's full name",
            'representative_contact' => "representative's contact number",
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'purpose.exists' => 'Choose a purpose from the list.',
            'purpose_other.required' => 'Type the purpose of your request.',
            'representative_contact.regex' => RequesterProfileForm::PHONE_MESSAGE,
        ];
    }

    public function selectedType(): ?CredentialType
    {
        return filled($this->credential_type_id) && is_numeric($this->credential_type_id)
            ? CredentialType::find((int) $this->credential_type_id)
            : null;
    }

    /**
     * @return array{credential_type_id: int, copies: int, purpose: string, receiving_institution: ?string}
     */
    public function validatedRequest(): array
    {
        /** @var array{credential_type_id: int|string, copies: int|string, purpose: string, purpose_other: ?string, receiving_institution: ?string} $data */
        $data = $this->validate();

        return [
            'credential_type_id' => (int) $data['credential_type_id'],
            'copies' => (int) $data['copies'],
            'purpose' => $data['purpose'] === self::OTHER_PURPOSE ? (string) $data['purpose_other'] : $data['purpose'],
            'receiving_institution' => filled($data['receiving_institution']) ? $data['receiving_institution'] : null,
        ];
    }

    /**
     * The declared representative, if any (spec 11).
     *
     * @return ?array{full_name: string, relationship: ?string, contact_number: ?string}
     */
    public function representative(): ?array
    {
        if ($this->requester_mode !== RequesterMode::Representative->value) {
            return null;
        }

        return [
            'full_name' => $this->representative_name,
            'relationship' => filled($this->representative_relationship) ? $this->representative_relationship : null,
            'contact_number' => filled($this->representative_contact) ? $this->representative_contact : null,
        ];
    }
}
