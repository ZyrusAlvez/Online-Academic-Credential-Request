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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Delay analysis (REQ-07-18): requests not released by their due date in the period, where the
 * document is, and why — the holding office is reported apart from the cause.
 */
final class DelayReport implements Report
{
    public function title(): string
    {
        return 'Delay Analysis Report';
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

        return self::overdue($filter)
            ->with(['credentialType', 'openVisit.department', 'openVisit.fromDepartment'])
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
                    self::causeOf($request)->label(),
                    $note?->reason,
                    $note?->corrective_action,
                ];
            })
            ->all();
    }

    /**
     * Eligible requests not released by their due date (REQ-07-18), most overdue first, with
     * what causeOf() needs.
     *
     * @return Builder<CredentialRequest>
     */
    public static function overdue(ReportFilter $filter): Builder
    {
        return PerformanceMetrics::eligible($filter)
            ->where(fn ($q) => $q->whereNull('released_at')->orWhereColumn('released_at', '>', 'effective_due_at'))
            ->with(['openVisit.department', 'visits.department', 'delayNotes'])
            ->orderBy('effective_due_at');
    }

    /**
     * The causes of the period's delays, largest first (REQ-34-03).
     *
     * @return Collection<string, int>
     */
    public static function causeCounts(ReportFilter $filter): Collection
    {
        return self::overdue($filter)->get()
            ->countBy(fn (CredentialRequest $request): string => self::causeOf($request)->label())
            ->sortDesc();
    }

    /**
     * The latest recorded reason's category, otherwise the cause inferred from the stage. Needs
     * openVisit.department, visits.department and delayNotes loaded.
     */
    public static function causeOf(CredentialRequest $request): DelayCategory
    {
        return $request->delayNotes->first()->category ?? self::inferredCause($request);
    }

    /**
     * Without a recorded reason, the stage tells the likely cause: the office holding the
     * document, or — once it has left the offices — the office where it stayed longest.
     */
    private static function inferredCause(CredentialRequest $request): DelayCategory
    {
        if ($request->status === RequestStatus::ReturnedForCustomerAction) {
            return DelayCategory::CustomerCompliance;
        }

        $visit = $request->openVisit ?? $request->visits->sortByDesc('working_days')->first();

        return $visit === null ? DelayCategory::Other : self::causeAt($visit);
    }

    private static function causeAt(OfficeVisit $visit): DelayCategory
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
