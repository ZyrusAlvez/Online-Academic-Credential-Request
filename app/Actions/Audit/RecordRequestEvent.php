<?php

namespace App\Actions\Audit;

use App\Enums\ActivityCategory;
use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Copies a request's ledger entry into the activity log (REQ-14-01). Called by
 * RequestWorkflow::record() and by the migration that copies the existing ledgers (REQ-14-14).
 */
final class RecordRequestEvent
{
    public function __construct(private RecordActivity $record) {}

    public function handle(RequestEvent $event, CredentialRequest $request, ?User $actor, ?CarbonInterface $at = null): void
    {
        $event->loadMissing('department');

        $description = $event->type->label();

        if ($event->department !== null) {
            $description .= " – {$event->department->name}";
        }

        if (filled($event->remarks)) {
            $description .= ": {$event->remarks}";
        }

        // Logged when it was recorded, saying when it happened and why (REQ-35-11). The marker comes
        // first, so the log's length limit can only cut the reason, which the ledger keeps whole.
        if ($event->isOverride()) {
            $description = "Admin override (happened {$event->created_at->format('M j, Y g:i A')}) – {$description} · Reason: {$event->override_reason}";
        }

        $this->record->handle(
            $actor,
            ActivityCategory::Request,
            "request.{$event->type->value}",
            $description,
            subject: $request,
            referenceNo: $request->reference_no,
            departmentId: $event->department_id,
            at: $at,
        );
    }
}
