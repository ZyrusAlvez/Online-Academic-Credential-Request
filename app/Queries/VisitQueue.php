<?php

namespace App\Queries;

use App\Enums\DepartmentQueue;
use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Office visits of one office, for the department's lists (spec 05, spec 24).
 */
class VisitQueue
{
    /** The columns a list can be sorted by (REQ-24-04). */
    public const SORTS = ['request', 'requester', 'since'];

    /**
     * One of the office's lists, searched, filtered and sorted (REQ-24-02..04).
     *
     * @return Builder<OfficeVisit>
     */
    public static function for(DepartmentQueue $queue, int $departmentId, string $search = '', ?int $typeId = null, string $sort = 'since', string $direction = ''): Builder
    {
        $query = match ($queue) {
            DepartmentQueue::Incoming => self::open($departmentId, VisitStatus::InTransit),
            DepartmentQueue::ToValidate => self::open($departmentId, VisitStatus::Received),
            // Approved or returned, still here until the Registrar collects it; the next office is
            // shown, so the route comes along (REQ-32-05).
            DepartmentQueue::Outgoing => self::open($departmentId, VisitStatus::Done)
                ->with(['step:id,task,position', 'credentialRequest.steps.department:id,name']),
            DepartmentQueue::History => self::history($departmentId),
        };

        $query->when(trim($search) !== '' || $typeId !== null, fn (Builder $q) => $q->whereHas('credentialRequest', fn (Builder $r) => RegistrarRequestFilter::matching($r, $search)
            ->when($typeId !== null, fn (Builder $r) => $r->where('credential_type_id', $typeId))));

        return self::sorted($query, $queue, $sort, $direction);
    }

    /**
     * Documents forwarded to the office (in transit) or in the office (received, or done and not
     * yet forwarded), for requests still being processed.
     *
     * @return Builder<OfficeVisit>
     */
    public static function open(int $departmentId, VisitStatus ...$statuses): Builder
    {
        return OfficeVisit::query()
            ->where('department_id', $departmentId)
            ->whereIn('status', $statuses)
            ->whereHas('credentialRequest', fn (Builder $q) => $q->whereIn('status', RequestStatus::processing()))
            ->with(self::relations());
    }

    /**
     * Visits the office has closed (completed, returned or bypassed).
     *
     * @return Builder<OfficeVisit>
     */
    public static function history(int $departmentId): Builder
    {
        return OfficeVisit::query()
            ->where('department_id', $departmentId)
            ->whereIn('status', [VisitStatus::Completed, VisitStatus::Returned, VisitStatus::Bypassed])
            ->with([...self::relations(), 'completer:id,first_name,middle_name,last_name,email', 'returnedTo:id,name']);
    }

    /**
     * Order by a whitelisted column; anything else falls back to the list's default (REQ-24-04).
     *
     * @param  Builder<OfficeVisit>  $query
     * @return Builder<OfficeVisit>
     */
    private static function sorted(Builder $query, DepartmentQueue $queue, string $sort, string $direction): Builder
    {
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : $queue->defaultDirection();
        $since = match ($queue) {
            DepartmentQueue::Incoming => 'forwarded_at',
            DepartmentQueue::ToValidate => 'received_at',
            DepartmentQueue::Outgoing => 'done_at',
            DepartmentQueue::History => 'completed_at',
        };

        $column = match ($sort) {
            'request' => self::requestColumn('reference_no'),
            'requester' => self::requestColumn('requester_name'),
            default => $since,
        };

        return $query->orderBy($column, $direction)->orderBy('office_visits.id', $direction);
    }

    /**
     * A column of the visit's request, for ordering.
     *
     * @return Builder<CredentialRequest>
     */
    private static function requestColumn(string $column): Builder
    {
        return CredentialRequest::query()->select($column)->whereColumn('credential_requests.id', 'office_visits.credential_request_id');
    }

    /**
     * @return list<string>
     */
    private static function relations(): array
    {
        return [
            'step:id,task',
            'fromDepartment:id,name,code',
            'credentialRequest:id,reference_no,user_id,requester_name,credential_type_id,college_id,status,effective_due_at',
            'credentialRequest.credentialType:id,name',
        ];
    }
}
