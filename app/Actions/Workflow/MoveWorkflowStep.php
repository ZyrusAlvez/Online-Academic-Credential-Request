<?php

namespace App\Actions\Workflow;

use App\Models\CredentialType;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

class MoveWorkflowStep
{
    /**
     * Swap the step with its neighbour. $direction is -1 (up) or 1 (down); moving past
     * either end is a no-op.
     */
    public function handle(WorkflowStep $step, int $direction): void
    {
        DB::transaction(function () use ($step, $direction): void {
            CredentialType::whereKey($step->credential_type_id)->lockForUpdate()->first();

            $step->refresh();
            $neighbour = WorkflowStep::where('credential_type_id', $step->credential_type_id)
                ->where('position', $step->position + ($direction < 0 ? -1 : 1))
                ->first();

            if ($neighbour === null) {
                return;
            }

            [$step->position, $neighbour->position] = [$neighbour->position, $step->position];
            $step->save();
            $neighbour->save();
        });
    }
}
