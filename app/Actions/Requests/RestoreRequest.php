<?php

namespace App\Actions\Requests;

use App\Actions\Audit\RecordActivity;
use App\Enums\ActivityCategory;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Admin restores a deleted request (REQ-37-05): every credential of its application comes back
 * exactly as it was. Logged in the activity log (REQ-37-06).
 */
class RestoreRequest
{
    public function __construct(private RecordActivity $recordActivity) {}

    public function handle(CredentialRequest $request, User $admin): void
    {
        DB::transaction(function () use ($request, $admin): void {
            $credentials = CredentialRequest::withTrashed()
                ->where('application_id', $request->application_id)
                ->lockForUpdate()
                ->get();

            if (! $credentials->contains(fn (CredentialRequest $credential): bool => $credential->trashed())) {
                throw new InvalidTransition('This request is not deleted.');
            }

            CredentialRequest::withTrashed()
                ->where('application_id', $request->application_id)
                ->update(['deleted_at' => null, 'deleted_by' => null, 'deletion_reason' => null]);

            $lead = $credentials->sortBy('item_no')->first() ?? $request;
            $referenceNo = $lead->application()->value('reference_no') ?? $lead->reference_no;

            $this->recordActivity->handle(
                $admin,
                ActivityCategory::Request,
                'request.restored',
                "Restored request {$referenceNo}",
                subject: $lead,
                referenceNo: $referenceNo,
            );
        });
    }
}
