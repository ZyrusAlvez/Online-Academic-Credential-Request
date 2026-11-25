<?php

namespace App\Actions\Requests;

use App\Actions\Audit\RecordActivity;
use App\Enums\ActivityCategory;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Admin deletes a request (REQ-37-01..03): every credential of its application is hidden but
 * kept, with who and why. Its ledger, files and visits are untouched, its status is unchanged and
 * no one is notified. Logged in the activity log (REQ-37-06).
 */
class DeleteRequest
{
    public function __construct(private RecordActivity $recordActivity) {}

    public function handle(CredentialRequest $request, User $admin, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidTransition('Give a reason for deleting the request.');
        }

        DB::transaction(function () use ($request, $admin, $reason): void {
            $credentials = CredentialRequest::withTrashed()
                ->where('application_id', $request->application_id)
                ->lockForUpdate()
                ->get();

            if ($credentials->contains(fn (CredentialRequest $credential): bool => $credential->trashed())) {
                throw new InvalidTransition('This request is already deleted.');
            }

            CredentialRequest::withTrashed()
                ->where('application_id', $request->application_id)
                ->update(['deleted_at' => now(), 'deleted_by' => $admin->id, 'deletion_reason' => $reason]);

            $lead = $credentials->sortBy('item_no')->first() ?? $request;
            $referenceNo = $lead->application()->value('reference_no') ?? $lead->reference_no;

            $this->recordActivity->handle(
                $admin,
                ActivityCategory::Request,
                'request.deleted',
                "Deleted request {$referenceNo}: {$reason}",
                subject: $lead,
                referenceNo: $referenceNo,
            );
        });
    }
}
