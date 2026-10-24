<?php

namespace App\Models;

use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One physical stay of a document at an office: forwarded → received → completed or returned
 * (spec 05). Never overwritten; a return and re-send create new visits.
 *
 * @property int $credential_request_id
 * @property int $request_step_id
 * @property int $department_id
 * @property int $visit_no
 * @property VisitPurpose $purpose
 * @property VisitStatus $status
 * @property ?int $from_department_id
 * @property Carbon $forwarded_at
 * @property ?Carbon $received_at
 * @property ?Carbon $completed_at
 * @property ?int $returned_to_department_id
 * @property ?string $return_reason
 * @property ?string $corrective_action
 * @property ?int $working_days
 */
class OfficeVisit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_no' => 'integer',
            'purpose' => VisitPurpose::class,
            'status' => VisitStatus::class,
            'forwarded_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'working_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CredentialRequest, $this>
     */
    public function credentialRequest(): BelongsTo
    {
        return $this->belongsTo(CredentialRequest::class);
    }

    /**
     * @return BelongsTo<RequestStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(RequestStep::class, 'request_step_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'returned_to_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
