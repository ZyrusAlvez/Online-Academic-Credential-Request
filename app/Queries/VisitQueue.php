<?php

namespace App\Queries;

use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\OfficeVisit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Office visits of one office, for the office queue and the registrar's Office of the Registrar
 * queue (spec 05).
 */
class VisitQueue
{
    /**
     * Documents forwarded to the office (in transit) or in the office (received), oldest first.
     *
     * @return Builder<OfficeVisit>
     */
    public static function open(int $departmentId, VisitStatus $status): Builder
    {
        return OfficeVisit::query()
            ->where('department_id', $departmentId)
            ->where('status', $status)
            ->whereHas('credentialRequest', fn (Builder $q) => $q->whereIn('status', RequestStatus::processing()))
            ->with(self::relations())
            ->oldest($status === VisitStatus::InTransit ? 'forwarded_at' : 'received_at')
            ->oldest('id');
    }

    /**
     * Visits the office has closed (completed, returned or bypassed), newest first.
     *
     * @return Builder<OfficeVisit>
     */
    public static function history(int $departmentId): Builder
    {
        return OfficeVisit::query()
            ->where('department_id', $departmentId)
            ->whereIn('status', [VisitStatus::Completed, VisitStatus::Returned, VisitStatus::Bypassed])
            ->with([...self::relations(), 'completer:id,first_name,middle_name,last_name'])
            ->latest('completed_at')
            ->latest('id');
    }

    /**
     * @return list<string>
     */
    private static function relations(): array
    {
        return [
            'step:id,task',
            'fromDepartment:id,name,code',
            'credentialRequest:id,reference_no,user_id,credential_type_id,college_id,status,effective_due_at',
            'credentialRequest.student:id,first_name,middle_name,last_name',
            'credentialRequest.credentialType:id,name',
        ];
    }
}
