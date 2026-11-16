<?php

namespace App\Livewire\Forms;

use App\Enums\RequesterMode;
use App\Models\CredentialType;
use App\Support\EmptyValue;
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

    public string $purpose = '';

    public string $purpose_other = '';

    public string $receiving_institution = '';

    public string $requester_mode = 'personal';

    public string $representative_name = '';

    public string $representative_relationship = '';

    public string $representative_contact = '';

    /** At most this many credentials in one application (REQ-27-10). */
    public const MAX_CREDENTIALS = 10;

    /**
     * The credentials after the first, in the same shape (REQ-27-10).
     *
     * @var list<array{credential_type_id: int|string|null, purpose: string, purpose_other: string, receiving_institution: string}>
     */
    public array $more = [];

    public function addCredential(): void
    {
        if (count($this->more) + 1 < self::MAX_CREDENTIALS) {
            $this->more[] = ['credential_type_id' => null, 'purpose' => '', 'purpose_other' => '', 'receiving_institution' => ''];
        }
    }

    public function removeCredential(int $index): void
    {
        unset($this->more[$index]);
        $this->more = array_values($this->more);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $representative = $this->requester_mode === RequesterMode::Representative->value;
        $requestable = CredentialType::requestable()->pluck('id')->all();

        $rules = [
            ...$this->entryRules('', $this->purpose, $this->selectedType(), $requestable),
            'requester_mode' => ['required', Rule::enum(RequesterMode::class)],
            'representative_name' => [Rule::requiredIf($representative), 'nullable', 'string', 'max:150'],
            'representative_relationship' => ['nullable', 'string', 'max:100'],
            'representative_contact' => ['nullable', 'string', 'max:20', RequesterProfileForm::PHONE_RULE],
            'more' => ['array', 'max:'.(self::MAX_CREDENTIALS - 1)],
        ];

        foreach ($this->more as $index => $entry) {
            $type = is_numeric($entry['credential_type_id']) ? CredentialType::find((int) $entry['credential_type_id']) : null;
            $rules = [...$rules, ...$this->entryRules("more.{$index}.", $entry['purpose'], $type, $requestable)];
            // The same credential once per application (REQ-27-01).
            $rules["more.{$index}.credential_type_id"][] = Rule::notIn($this->chosenTypes(except: $index + 1));
        }

        return $rules;
    }

    /**
     * One credential's rules, the first's at the top level and the others' under `more.N.`.
     *
     * @param  list<int>  $requestable
     * @return array<string, list<mixed>>
     */
    private function entryRules(string $prefix, string $purpose, ?CredentialType $type, array $requestable): array
    {
        $other = $purpose === self::OTHER_PURPOSE;

        return [
            "{$prefix}credential_type_id" => ['required', 'integer', Rule::in($requestable)],
            "{$prefix}purpose" => $other ? ['required', 'string'] : ['required', 'string', Rule::exists('purposes', 'name')->where('is_active', true)],
            "{$prefix}purpose_other" => [Rule::requiredIf($other), 'nullable', 'string', 'max:500'],
            "{$prefix}receiving_institution" => [Rule::requiredIf((bool) $type?->requires_receiving_institution), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The credentials chosen before entry $position (0 is the first).
     *
     * @return list<int>
     */
    private function chosenTypes(int $except): array
    {
        $chosen = [$this->credential_type_id, ...array_column($this->more, 'credential_type_id')];

        return array_values(array_map(intval(...), array_filter(array_slice($chosen, 0, $except), is_numeric(...))));
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $attributes = [
            'credential_type_id' => 'credential',
            'purpose_other' => 'purpose',
            'receiving_institution' => 'receiving school',
            'representative_name' => "representative's full name",
            'representative_contact' => "representative's contact number",
        ];

        foreach (array_keys($this->more) as $index) {
            $n = $index + 2;
            $attributes["more.{$index}.credential_type_id"] = "credential {$n}";
            $attributes["more.{$index}.purpose"] = "purpose of credential {$n}";
            $attributes["more.{$index}.purpose_other"] = "purpose of credential {$n}";
            $attributes["more.{$index}.receiving_institution"] = "receiving school of credential {$n}";
        }

        return $attributes;
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
            'more.*.purpose.exists' => 'Choose a purpose from the list.',
            'more.*.credential_type_id.not_in' => 'Each credential can be requested only once.',
        ];
    }

    public function selectedType(): ?CredentialType
    {
        return filled($this->credential_type_id) && is_numeric($this->credential_type_id)
            ? CredentialType::find((int) $this->credential_type_id)
            : null;
    }

    /**
     * @return array{credential_type_id: int, purpose: string, receiving_institution: ?string}
     */
    public function validatedRequest(): array
    {
        /** @var array{credential_type_id: int|string, purpose: string, purpose_other: ?string, receiving_institution: ?string} $data */
        $data = $this->validate();

        return [
            'credential_type_id' => (int) $data['credential_type_id'],
            'purpose' => $data['purpose'] === self::OTHER_PURPOSE ? (string) $data['purpose_other'] : $data['purpose'],
            'receiving_institution' => filled($data['receiving_institution']) ? $data['receiving_institution'] : null,
        ];
    }

    /**
     * Section 1 of the summary: the first credential under "1. What Do You Need?", each other under
     * "Credential N", then the total when there are several (REQ-15-17, REQ-27-10).
     *
     * @return array<string, array<string, string|EmptyValue>>
     */
    public function summary(): array
    {
        $entries = [
            ['credential_type_id' => $this->credential_type_id, 'purpose' => $this->purpose, 'purpose_other' => $this->purpose_other, 'receiving_institution' => $this->receiving_institution],
            ...$this->more,
        ];
        $types = CredentialType::query()->whereIn('id', array_filter(array_column($entries, 'credential_type_id'), is_numeric(...)))->get()->keyBy('id');
        $several = count($entries) > 1;
        $sections = [];

        foreach ($entries as $index => $entry) {
            $type = $types->get((int) $entry['credential_type_id']);
            $receivingLabel = $type?->requires_receiving_institution ? __('Receiving school') : __('Receiving school, employer or agency');

            $rows = [
                __('Credential') => (string) $type?->name,
                __('Purpose') => $entry['purpose'] === self::OTHER_PURPOSE ? $entry['purpose_other'] : $entry['purpose'],
                // Left blank: shown as a grey "Not provided", not as an entry (REQ-15-19).
                $receivingLabel => filled($entry['receiving_institution']) ? $entry['receiving_institution'] : EmptyValue::notProvided(),
            ];

            if ($several) {
                $rows[__('Fee')] = $type?->requires_payment && $type->fee !== null ? '₱'.number_format((float) $type->fee, 2) : __('None');
            }

            $sections[$index === 0 ? __('1. What Do You Need?') : __('Credential :n', ['n' => $index + 1])] = $rows;
        }

        if ($several) {
            $total = $types->filter(fn (CredentialType $type): bool => $type->requires_payment)->sum(fn (CredentialType $type): float => (float) $type->fee);
            $sections[__('Total')] = [__('Amount due') => '₱'.number_format($total, 2)];
        }

        return $sections;
    }

    /**
     * Every credential asked for, the first then the others (REQ-27-10).
     *
     * @return list<array{credential_type_id: int, purpose: string, receiving_institution: ?string}>
     */
    public function validatedItems(): array
    {
        $first = $this->validatedRequest();

        return [$first, ...array_map(fn (array $entry): array => [
            'credential_type_id' => (int) $entry['credential_type_id'],
            'purpose' => $entry['purpose'] === self::OTHER_PURPOSE ? (string) $entry['purpose_other'] : $entry['purpose'],
            'receiving_institution' => filled($entry['receiving_institution']) ? $entry['receiving_institution'] : null,
        ], $this->more)];
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
