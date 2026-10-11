<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Models\RequestStep;
use Illuminate\Database\Eloquent\Builder;

/**
 * Steps waiting on a department, used by department and registrar queues.
 */
class StepQueue
{
    /**
     * Current steps of in-progress requests for the department, oldest first.
     *
     * @return Builder<RequestStep>
     */
    public static function waiting(int $departmentId, StepStatus $status = StepStatus::Pending): Builder
    {
        return RequestStep::query()
            ->where('department_id', $departmentId)
            ->where('status', $status)
            ->whereHas('credentialRequest', fn (Builder $q) => $q->where('status', RequestStatus::InProgress))
            ->with([
                'credentialRequest:id,reference_no,user_id,credential_type_id,college_id,status',
                'credentialRequest.student:id,first_name,middle_name,last_name',
                'credentialRequest.credentialType:id,name',
                'credentialRequest.college:id,name,code',
            ])
            ->oldest('updated_at')
            ->oldest('id');
    }

    /**
     * Steps the department has completed (signed or skipped), newest first.
     *
     * @return Builder<RequestStep>
     */
    public static function history(int $departmentId): Builder
    {
        return RequestStep::query()
            ->where('department_id', $departmentId)
            ->whereIn('status', [StepStatus::Signed, StepStatus::Skipped])
            ->with([
                'actor:id,first_name,middle_name,last_name',
                'credentialRequest:id,reference_no,user_id,credential_type_id,status',
                'credentialRequest.student:id,first_name,middle_name,last_name',
                'credentialRequest.credentialType:id,name',
            ])
            ->latest('acted_at')
            ->latest('id');
    }
}
