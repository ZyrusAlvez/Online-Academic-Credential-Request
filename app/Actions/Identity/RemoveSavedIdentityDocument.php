<?php

namespace App\Actions\Identity;

use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RemoveSavedIdentityDocument
{
    /**
     * Take the requester's saved ID off their profile, so they can give another (REQ-15-18).
     * Requests sent with it keep it (REQ-15-08); one no request uses is deleted with its file.
     */
    public function handle(User $user): void
    {
        $path = DB::transaction(function () use ($user): ?string {
            $document = $user->latestIdentityDocument()->first();

            if ($document === null) {
                return null;
            }

            if (CredentialRequest::query()->where('identity_document_id', $document->id)->exists()) {
                $document->forceFill(['removed_at' => now()])->save();

                return null;
            }

            $document->delete();

            return $document->path;
        });

        // Only once the row is gone for good, so a rolled-back delete never loses the file.
        if ($path !== null) {
            Storage::disk(RequestWorkflow::proofDisk())->delete($path);
        }
    }
}
