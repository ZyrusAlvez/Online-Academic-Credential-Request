<?php

namespace App\Models;

use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
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
 * @property ?Carbon $done_at the office's task was done (spec 17)
 * @property ?Carbon $completed_at the paper left the office, or the final check closed it
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
            'done_at' => 'datetime',
            'completed_at' => 'datetime',
            'working_days' => 'integer',
        ];
    }

    /**
     * Where the paper goes when it leaves this office: a corrected document back to the office
     * that returned it (its step), otherwise the route's next waiting step; null after the final
     * quality check (REQ-17-07). Reads the loaded `step` and `credentialRequest.steps`.
     */
    public function nextStep(): ?RequestStep
    {
        $steps = $this->credentialRequest->steps->sortBy('position');

        if ($this->purpose === VisitPurpose::Correction) {
            return $steps->firstWhere('id', $this->request_step_id);
        }

        return $steps->first(fn (RequestStep $step): bool => $step->position > $this->step->position && $step->status === StepStatus::Waiting);
    }

    /**
     * The office that has the paper during this visit. A forwarded paper stays with the office
     * that sent it until this office accepts receipt; the forward made at acceptance leaves the
     * Office of the Registrar (REQ-17-18). Reads the loaded `fromDepartment` and `department`.
     */
    public function holder(): Department
    {
        if ($this->status !== VisitStatus::InTransit) {
            return $this->department;
        }

        return $this->fromDepartment ?? Department::registrar() ?? $this->department;
    }

    /**
     * Open visits whose paper the office has now (REQ-17-18): in the office, or forwarded by it
     * and not yet received.
     *
     * @param  Builder<OfficeVisit>  $query
     */
    public static function whereHeldBy(Builder $query, int $departmentId): void
    {
        $fromRegistrar = Department::registrar()?->id === $departmentId;

        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->whereIn('office_visits.status', VisitStatus::inOffice())->where('office_visits.department_id', $departmentId))
            ->orWhere(fn (Builder $q) => $q->where('office_visits.status', VisitStatus::InTransit)
                ->where(fn (Builder $q) => $q->where('office_visits.from_department_id', $departmentId)
                    ->when($fromRegistrar, fn (Builder $q) => $q->orWhereNull('office_visits.from_department_id')))));
    }

    /**
     * The office holding the paper as SQL, for counts grouped by office (REQ-17-18).
     *
     * @return array{0: string, 1: list<int|string>}
     */
    public static function holderSql(): array
    {
        return [
            'CASE WHEN office_visits.status = ? THEN COALESCE(office_visits.from_department_id, ?) ELSE office_visits.department_id END',
            [VisitStatus::InTransit->value, Department::registrar()->id ?? 0],
        ];
    }

    /**
     * The registrar can send the paper on from here: its office's task is done, or it is the
     * Office of the Registrar's own task (preparation or a correction, not the final quality
     * check), which forwarding marks done (REQ-17-12). Reads the loaded `department` and `step`.
     */
    public function isForwardable(): bool
    {
        return match ($this->status) {
            VisitStatus::Done => true,
            VisitStatus::Received => $this->department->code === Department::REGISTRAR_CODE
                && ($this->purpose === VisitPurpose::Correction || $this->step->task !== RouteTask::FinalCheck),
            default => false,
        };
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
    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
