<?php

namespace App\Reports;

use App\Enums\DelayCategory;
use App\Enums\DepartmentType;
use App\Enums\RequestStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Services\WorkingCalendar;

/**
 * Delay analysis (REQ-07-18): requests not released by their due date in the period, where the
 * document is, and why — the holding office is reported apart from the cause.
 */
final class DelayReport implements Report
{
    public function title(): string
    {
        return 'Delay analysis report';
    }

    public function description(): string
    {
        return 'Overdue requests due in the period: where they are, how late, how often returned, the recorded or inferred cause and the corrective action.';
    }

    public function columns(): array
    {
        return ['Tracking no.', 'Credential', 'Accepted', 'Due', 'Released', 'Holding office', 'Stage', 'Working days late', 'Returns', 'Cause', 'Recorded reason', 'Corrective action'];
    }

    public function rows(ReportFilter $filter): iterable
    {
        $calendar = app(WorkingCalendar::class);

        return PerformanceMetrics::eligible($filter)
            ->where(fn ($q) => $q->whereNull('released_at')->orWhereColumn('released_at', '>', 'effective_due_at'))
            ->with(['credentialType', 'openVisit.department', 'visits.department', 'delayNotes'])
            ->orderBy('effective_due_at')
            ->get()
            ->map(function (CredentialRequest $request) use ($calendar): array {
                $note = $request->delayNotes->first();   // latest first
                $holding = $request->openVisit?->department->name
                    ?? ($request->status === RequestStatus::ReturnedForCustomerAction ? 'Requester' : 'Office of the Registrar');

                return [
                    $request->reference_no,
                    $request->credentialType->name,
                    $request->accepted_at?->format('Y-m-d'),
                    $request->effective_due_at?->format('Y-m-d'),
                    $request->released_at?->format('Y-m-d'),
                    $request->status === RequestStatus::Released ? '—' : $holding,
                    $request->status->label(),
                    $request->effective_due_at === null ? null : $calendar->workingDaysBetween($request->effective_due_at, $request->released_at ?? now()),
                    $request->visits->where('status', VisitStatus::Returned)->count(),
                    ($note->category ?? $this->inferredCause($request))->label(),
                    $note?->reason,
                    $note?->corrective_action,
                ];
            })
            ->all();
    }

    /**
     * Without a recorded reason, the stage tells the likely cause: the office holding the
     * document, or — once it has left the offices — the office where it stayed longest.
     */
    private function inferredCause(CredentialRequest $request): DelayCategory
    {
        if ($request->status === RequestStatus::ReturnedForCustomerAction) {
            return DelayCategory::CustomerCompliance;
        }

        $visit = $request->openVisit ?? $request->visits->sortByDesc('working_days')->first();

        return $visit === null ? DelayCategory::Other : $this->causeAt($visit);
    }

    private function causeAt(OfficeVisit $visit): DelayCategory
    {
        if ($visit->purpose === VisitPurpose::Correction) {
            return DelayCategory::DocumentCorrection;
        }

        return match (true) {
            $visit->department->type === DepartmentType::College => DelayCategory::AcademicDepartment,
            $visit->department->code === 'VPF' => DelayCategory::DocumentaryStamp,
            in_array($visit->department->code, ['CHAN', 'PRES'], true) => DelayCategory::PendingSignature,
            $visit->department->code === Department::REGISTRAR_CODE => DelayCategory::RegistrarEvaluation,
            default => DelayCategory::Other,
        };
    }
}
