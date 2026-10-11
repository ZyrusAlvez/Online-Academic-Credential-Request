<?php

namespace App\Actions\Workflow;

use App\Models\CredentialType;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

class RemoveWorkflowStep
{
    public function handle(WorkflowStep $step): void
    {
        DB::transaction(function () use ($step): void {
            /** @var CredentialType $type */
            $type = CredentialType::whereKey($step->credential_type_id)->lockForUpdate()->firstOrFail();

            $step->delete();
            $type->resequenceSteps();
        });
    }
}
