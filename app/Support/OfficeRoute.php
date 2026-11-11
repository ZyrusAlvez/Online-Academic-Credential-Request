<?php

namespace App\Support;

use App\Enums\DepartmentType;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\RequestStep;
use App\Models\WorkflowStep;
use Carbon\CarbonInterface;

/**
 * The offices a document goes through, in order, ending with the Registrar's final quality check
 * (REQ-09-11, REQ-06-17): a credential's route before acceptance, a request's own steps after.
 */
class OfficeRoute
{
    /**
     * A credential's route, as New Request shows it (REQ-15-15). Expects `steps.department`.
     *
     * @return list<array{office: string, task: string}>
     */
    public static function planned(CredentialType $type, ?Department $college, ?string $registrarOffice = null): array
    {
        $route = $type->steps->map(fn (WorkflowStep $step): array => [
            'office' => $step->is_student_college
                ? ($college ? self::dean($college) : __('Dean of your college'))
                : (string) $step->department?->name,
            'task' => $step->task->label(),
        ])->values()->all();

        $route[] = [
            'office' => $registrarOffice ?? Department::registrar()->name ?? __('Office of the Registrar'),
            'task' => RouteTask::FinalCheck->label(),
        ];

        return $route;
    }

    /**
     * Where the request's document goes and has been (REQ-06-17). Loads what is missing: the
     * request's steps, or before acceptance the credential's route and the requester's college.
     *
     * @return list<array{office: string, task: string, state: 'done'|'current'|'upcoming', completed_at: ?CarbonInterface}>
     */
    public static function forRequest(CredentialRequest $request): array
    {
        $request->loadMissing('steps.department');

        if ($request->steps->isEmpty()) {
            $request->loadMissing(['credentialType.steps.department', 'college']);

            return array_map(
                fn (array $stop): array => $stop + ['state' => 'upcoming', 'completed_at' => null],
                self::planned($request->credentialType, $request->college),
            );
        }

        return $request->steps->sortBy('position')->map(fn (RequestStep $step): array => [
            'office' => $step->department->type === DepartmentType::College ? self::dean($step->department) : $step->department->name,
            'task' => $step->task->label(),
            'state' => match (true) {
                $step->status->isDone() => 'done',
                $step->status === StepStatus::Current => 'current',
                default => 'upcoming',
            },
            'completed_at' => $step->completed_at,
        ])->values()->all();
    }

    private static function dean(Department $college): string
    {
        return __('Dean, :college', ['college' => $college->name]);
    }
}
