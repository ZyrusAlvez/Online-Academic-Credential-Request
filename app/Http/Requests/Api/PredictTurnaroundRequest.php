<?php

namespace App\Http\Requests\Api;

use App\Enums\StudentStatus;
use App\Models\CredentialType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PredictTurnaroundRequest extends FormRequest
{
    /** Deactivated registrars keep no API access, even with an unexpired token. */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credential_type_id' => ['required', 'integer', Rule::exists(CredentialType::class, 'id')->where('is_active', true)],
            'copies' => ['required', 'integer', 'between:1,10'],
            'student_status' => ['required', Rule::enum(StudentStatus::class)],
            'routed_at' => ['nullable', 'date'],
            'open_workload' => ['nullable', 'integer', 'between:0,100000'],
        ];
    }
}
