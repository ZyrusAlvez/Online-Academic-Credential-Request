<?php

namespace App\Actions\WalkIn;

use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The Registrar records a walk-in client's request, already validated at the counter
 * (REQ-26-03, 04).
 */
class RecordWalkInRequest
{
    public function __construct(private readonly RequestWorkflow $workflow) {}

    /**
     * @param  array{credential_type_id: int, purpose: string, receiving_institution?: ?string}|list<array{credential_type_id: int, purpose: string, receiving_institution?: ?string}>  $data  one credential or several (REQ-27-10)
     * @param  array{first_name: string, middle_name: ?string, last_name: string, sex: string, maiden_name: ?string, email: string, contact_number: string, academic_status: string, college_id: int, program: string, student_number: ?string, attended_from: int, attended_to: int}  $details
     */
    public function handle(User $registrar, array $data, array $details): CredentialRequest
    {
        if (! $registrar->hasRole(Role::Registrar)) {
            throw new AuthorizationException('Only the Registrar records walk-in requests.');
        }

        return $this->workflow->submitWalkIn($registrar, $data, $details);
    }
}
