<?php

namespace App\Livewire\Forms;

use App\Enums\DepartmentType;
use App\Enums\StudentStatus;
use App\Models\CredentialType;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CredentialRequestForm extends Form
{
    // Numeric inputs accept strings so invalid input reaches validation instead of a TypeError.
    public int|string|null $credential_type_id = null;

    public int|string|null $copies = 1;

    public string $purpose = '';

    public string $student_number = '';

    public int|string|null $college_id = null;

    public string $program = '';

    public string $student_status = '';

    public string $last_school_year = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'credential_type_id' => ['required', 'integer', Rule::in(CredentialType::requestable()->pluck('id')->all())],
            'copies' => ['required', 'integer', 'between:1,10'],
            'purpose' => ['required', 'string', 'max:500'],
            'student_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'college_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('type', DepartmentType::College->value)->where('is_active', true),
            ],
            'program' => ['required', 'string', 'max:150'],
            'student_status' => ['required', Rule::enum(StudentStatus::class)],
            'last_school_year' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{4})$/', $value, $years)
                    || (int) $years[2] !== (int) $years[1] + 1
                    || (int) $years[1] < 1950
                    || (int) $years[1] > (int) now()->year) {
                    $fail('Enter the school year as YYYY-YYYY, e.g. 2023-2024.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'credential_type_id' => 'credential',
            'college_id' => 'college',
            'student_status' => 'status',
            'last_school_year' => 'last school year attended',
        ];
    }

    /**
     * @return array{credential_type_id: int, college_id: int, student_number: string, program: string, student_status: string, last_school_year: string, purpose: string, copies: int}
     */
    public function validated(): array
    {
        /** @var array{credential_type_id: int|string, college_id: int|string, student_number: string, program: string, student_status: string, last_school_year: string, purpose: string, copies: int|string} $data */
        $data = $this->validate();

        return [
            ...$data,
            'credential_type_id' => (int) $data['credential_type_id'],
            'college_id' => (int) $data['college_id'],
            'copies' => (int) $data['copies'],
        ];
    }
}
