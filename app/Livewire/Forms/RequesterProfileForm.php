<?php

namespace App\Livewire\Forms;

use App\Enums\AcademicStatus;
use App\Enums\DepartmentType;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * A requester's name, contact number, academic information, valid ID and privacy consent, checked
 * first and then saved together from the summary. Shared by New Request and Settings → Profile
 * (REQ-01-16, REQ-15-03..07); never locked, as each request keeps its own copy (REQ-15-08).
 */
class RequesterProfileForm extends Form
{
    /** Philippine mobile number: exactly 11 digits, e.g. 09171234567. */
    public const PHONE_RULE = 'regex:/^[0-9]{11}$/';

    public const PHONE_MESSAGE = 'Enter an 11-digit mobile number, e.g. 09171234567.';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $contact_number = '';

    public string $academic_status = '';

    public int|string|null $college_id = null;

    public string $program = '';

    public string $student_number = '';

    // Numeric inputs accept strings so invalid input reaches validation instead of a TypeError.
    public int|string|null $attended_from = null;

    public int|string|null $attended_to = null;

    public string $id_type = '';

    /** @var UploadedFile|null */
    public $id_file = null;

    public bool $privacy_consent = false;

    public function load(User $user): void
    {
        $this->first_name = $user->first_name ?? '';
        $this->middle_name = $user->middle_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->contact_number = $user->contact_number ?? '';
        $this->academic_status = $user->academic_status->value ?? '';
        $this->college_id = $user->college_id;
        $this->program = $user->program ?? '';
        $this->student_number = $user->student_number ?? '';
        $this->attended_from = $user->attended_from;
        $this->attended_to = $user->attended_to;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $thisYear = (int) now()->year;
        $needsId = ! $this->user()->latestIdentityDocument()->exists();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', self::PHONE_RULE],
            'academic_status' => ['required', Rule::enum(AcademicStatus::class)],
            'college_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('type', DepartmentType::College->value)->where('is_active', true),
            ],
            'program' => [
                'required', 'string', 'max:150',
                Rule::exists('programs', 'name')->where('department_id', (int) $this->college_id)->where('is_active', true),
            ],
            'student_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'attended_from' => ['required', 'integer', 'between:1950,'.$thisYear],
            'attended_to' => ['required', 'integer', 'between:1950,'.$thisYear, 'gte:attended_from'],
            // A saved ID is used unless another is chosen; without one, an ID is required (REQ-15-04).
            'id_type' => [Rule::requiredIf($needsId), 'nullable', 'required_with:id_file', Rule::in(IdentityDocument::ID_TYPES)],
            'id_file' => [Rule::requiredIf($needsId), 'nullable', 'required_with:id_type', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            // Ticked on every summary before anything is saved or submitted (REQ-15-05, 07).
            'privacy_consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'academic_status' => 'status',
            'college_id' => 'college',
            'program' => 'program or strand',
            'attended_from' => 'first year attended',
            'attended_to' => 'last year attended',
            'id_type' => 'type of ID',
            'id_file' => 'valid ID',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'contact_number.regex' => self::PHONE_MESSAGE,
            'privacy_consent.accepted' => 'Tick the box to agree to the processing of your personal data before you continue.',
        ];
    }

    /**
     * Check every entry on the form; the consent comes later, on the summary (REQ-15-05).
     */
    public function validateDetails(): void
    {
        $this->validate(Arr::except($this->rules(), 'privacy_consent'));
    }

    /**
     * The entries as the requester will read them on the summary, by section (REQ-01-25).
     *
     * @return array<string, array<string, string>>
     */
    public function summary(): array
    {
        return [
            'Personal information' => [
                'First name' => $this->first_name,
                'Middle name' => filled($this->middle_name) ? $this->middle_name : 'Not provided',
                'Last name' => $this->last_name,
                'Contact number' => $this->contact_number,
            ],
            'Academic information' => [
                'Status' => (string) AcademicStatus::tryFrom($this->academic_status)?->label(),
                'College' => (string) Department::query()->whereKey($this->college_id)->value('name'),
                'Program' => $this->program,
                'Student number' => filled($this->student_number) ? $this->student_number : 'Not provided',
                'Years attended' => "{$this->attended_from} to {$this->attended_to}",
            ],
        ];
    }

    /**
     * Save the profile and record the privacy consent; returns the newly chosen ID, if any, for the
     * caller to submit.
     *
     * @return ?array{type: string, file: UploadedFile}
     */
    public function save(User $user): ?array
    {
        $validated = $this->validate();

        $user->fill([
            'first_name' => $validated['first_name'],
            'middle_name' => filled($validated['middle_name']) ? $validated['middle_name'] : null,
            'last_name' => $validated['last_name'],
            'contact_number' => $validated['contact_number'],
            'academic_status' => $validated['academic_status'],
            'college_id' => (int) $validated['college_id'],
            'program' => $validated['program'],
            'student_number' => filled($validated['student_number']) ? $validated['student_number'] : null,
            'attended_from' => (int) $validated['attended_from'],
            'attended_to' => (int) $validated['attended_to'],
        ]);

        if ($user->privacy_consented_at === null) {
            $user->forceFill(['privacy_consented_at' => now()]);
        }

        $user->save();

        return $this->id_file instanceof UploadedFile
            ? ['type' => $this->id_type, 'file' => $this->id_file]
            : null;
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
