<?php

namespace App\Livewire\Forms;

use App\Enums\AcademicStatus;
use App\Enums\DepartmentType;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * A requester's contact number and academic information (REQ-01-16).
 */
class RequesterProfileForm extends Form
{
    /** Philippine mobile number: exactly 11 digits, e.g. 09171234567. */
    public const PHONE_RULE = 'regex:/^[0-9]{11}$/';

    public const PHONE_MESSAGE = 'Enter an 11-digit mobile number, e.g. 09171234567.';

    public string $contact_number = '';

    public string $academic_status = '';

    public int|string|null $college_id = null;

    public string $program = '';

    public string $student_number = '';

    // Numeric inputs accept strings so invalid input reaches validation instead of a TypeError.
    public int|string|null $attended_from = null;

    public int|string|null $attended_to = null;

    public function load(User $user): void
    {
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

        return [
            'contact_number' => ['required', 'string', 'max:20', self::PHONE_RULE],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['contact_number.regex' => self::PHONE_MESSAGE];
    }

    public function save(User $user): void
    {
        $validated = $this->validate();

        $user->fill([
            'contact_number' => $validated['contact_number'],
            'academic_status' => $validated['academic_status'],
            'college_id' => (int) $validated['college_id'],
            'program' => $validated['program'],
            'student_number' => filled($validated['student_number']) ? $validated['student_number'] : null,
            'attended_from' => (int) $validated['attended_from'],
            'attended_to' => (int) $validated['attended_to'],
        ])->save();
    }
}
