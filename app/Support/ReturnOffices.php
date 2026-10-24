<?php

namespace App\Support;

use App\Enums\StepStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use Illuminate\Support\Collection;

/**
 * Offices a document can be returned to for correction: those it has already passed through,
 * nearest first (REQ-05-06). Expects `steps.department` and `openVisit.step` to be loaded.
 */
class ReturnOffices
{
    /**
     * @return Collection<int, Department>
     */
    public static function for(CredentialRequest $request): Collection
    {
        $current = $request->openVisit?->step;

        if ($current === null) {
            return collect();
        }

        return $request->steps
            ->filter(fn ($step) => $step->position < $current->position && $step->status === StepStatus::Completed)
            ->sortByDesc('position')
            ->map(fn ($step) => $step->department)
            ->unique('id')
            ->values();
    }
}
