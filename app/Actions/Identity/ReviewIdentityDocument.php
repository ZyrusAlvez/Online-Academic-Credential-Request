<?php

namespace App\Actions\Identity;

use App\Enums\IdentityStatus;
use App\Enums\ReviewStatus;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Notifications\IdentityReviewed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewIdentityDocument
{
    /**
     * Verify or reject a requester's pending ID and tell them the result (REQ-01-19).
     *
     * @throws ValidationException when no reason is given for a rejection or the ID was already reviewed
     */
    public function handle(IdentityDocument $document, User $registrar, bool $verified, ?string $remarks = null): void
    {
        if (! $verified && blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Give the reason for rejecting this ID.']);
        }

        DB::transaction(function () use ($document, $registrar, $verified, $remarks): void {
            /** @var IdentityDocument $locked */
            $locked = IdentityDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReviewStatus::Pending) {
                throw ValidationException::withMessages(['remarks' => 'This ID has already been reviewed.']);
            }

            $locked->forceFill([
                'status' => $verified ? ReviewStatus::Verified : ReviewStatus::Rejected,
                'reviewed_by' => $registrar->id,
                'reviewed_at' => now(),
                'remarks' => filled($remarks) ? $remarks : null,
            ])->save();

            $owner = $locked->user()->firstOrFail();

            // Only the newest upload decides the account's status.
            if ($owner->latestIdentityDocument()->value('id') === $locked->id) {
                $owner->forceFill([
                    'identity_status' => $verified ? IdentityStatus::Verified : IdentityStatus::Rejected,
                    'identity_verified_at' => $verified ? now() : null,
                ])->save();
            }

            DB::afterCommit(fn () => $owner->notify(new IdentityReviewed($locked)));
        });
    }
}
