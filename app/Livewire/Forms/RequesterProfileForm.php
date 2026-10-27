<?php

namespace App\Livewire\Forms;

use App\Enums\AcademicStatus;
use App\Enums\DepartmentType;
use App\Enums\IdentityStatus;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * A requester's name, contact number, academic information, valid ID and privacy consent, saved
 * together (REQ-01-16, REQ-01-17, REQ-01-22).
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
        $user = $this->user();

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
            'program' => ['required', 'string', 'max:150'],
            'student_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'attended_from' => ['required', 'integer', 'between:1950,'.$thisYear],
            'attended_to' => ['required', 'integer', 'between:1950,'.$thisYear, 'gte:attended_from'],
            // The ID is optional on each save; the profile stays incomplete until one is uploaded.
            'id_type' => ['nullable', 'required_with:id_file', Rule::in(IdentityDocument::ID_TYPES)],
            'id_file' => [
                Rule::prohibitedIf($user->identity_status === IdentityStatus::Verified),
                'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120',
            ],
            'privacy_consent' => $user->privacy_consented_at === null ? ['accepted'] : [],
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
            'privacy_consent.accepted' => 'You must agree to the processing of your personal data to save your profile.',
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
