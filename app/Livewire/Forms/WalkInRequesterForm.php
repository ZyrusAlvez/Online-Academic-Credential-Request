<?php

namespace App\Livewire\Forms;

use App\Support\EmptyValue;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * A walk-in client's personal and academic information, entered by the Registrar (REQ-26-02): New
 * Request's fields and checks, with the client's email and without the valid ID or the privacy
 * consent. Nothing is saved to an account; the request keeps its own copy (REQ-26-04).
 */
class WalkInRequesterForm extends RequesterProfileForm
{
    public string $email = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), ['id_type', 'id_file', 'privacy_consent']),
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<string, string|EmptyValue>>
     */
    public function summary(): array
    {
        $summary = parent::summary();
        $summary['Personal Information']['Email'] = Str::lower($this->email);

        return $summary;
    }

    /**
     * The checked entries, as the request's copy of the requester's details (REQ-26-04).
     *
     * @return array{first_name: string, middle_name: ?string, last_name: string, sex: string, email: string, contact_number: string, academic_status: string, college_id: int, program: string, student_number: ?string, attended_from: int, attended_to: int}
     */
    public function details(): array
    {
        /** @var array<string, string|int|null> $validated */
        $validated = $this->validate();

        return [
            'first_name' => (string) $validated['first_name'],
            'middle_name' => filled($validated['middle_name']) ? (string) $validated['middle_name'] : null,
            'last_name' => (string) $validated['last_name'],
            'sex' => (string) $validated['sex'],
            'email' => Str::lower((string) $validated['email']),
            'contact_number' => $this->e164(),
            'academic_status' => (string) $validated['academic_status'],
            'college_id' => (int) $validated['college_id'],
            'program' => (string) $validated['program'],
            'student_number' => filled($validated['student_number']) ? (string) $validated['student_number'] : null,
            'attended_from' => (int) $validated['attended_from'],
            'attended_to' => (int) $validated['attended_to'],
        ];
    }
}
