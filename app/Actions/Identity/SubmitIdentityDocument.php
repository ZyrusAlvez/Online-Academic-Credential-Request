<?php

namespace App\Actions\Identity;

use App\Enums\IdentityStatus;
use App\Enums\ReviewStatus;
use App\Models\IdentityDocument;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitIdentityDocument
{
    /**
     * Store a requester's valid ID privately and mark their identity as pending (REQ-01-17).
     *
     * @throws ValidationException when the identity is already verified
     */
    public function handle(User $user, string $idType, UploadedFile $file): IdentityDocument
    {
        if ($user->identity_status === IdentityStatus::Verified) {
            throw ValidationException::withMessages(['idFile' => 'Your identity is already verified.']);
        }

        $disk = RequestWorkflow::proofDisk();
        $path = $file->storeAs("identity-documents/{$user->id}", Str::uuid().'.'.$file->extension(), $disk);

        if ($path === false) {
            throw ValidationException::withMessages(['idFile' => 'The file could not be saved. Please try again.']);
        }

        try {
            return DB::transaction(function () use ($user, $idType, $file, $path): IdentityDocument {
                $document = $user->identityDocuments()->make([
                    'id_type' => $idType,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
                $document->status = ReviewStatus::Pending;
                $document->save();

                $user->forceFill(['identity_status' => IdentityStatus::Pending, 'identity_verified_at' => null])->save();

                return $document;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }
}
