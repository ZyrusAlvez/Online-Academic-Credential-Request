<?php

namespace App\Actions\Identity;

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
     * Store a requester's valid ID privately; it becomes their saved ID (REQ-01-17, REQ-15-04).
     * Earlier uploads are kept. A caller whose own, outer transaction fails afterwards deletes the
     * stored file itself.
     *
     * @throws ValidationException when the file cannot be stored
     */
    public function handle(User $user, string $idType, UploadedFile $file): IdentityDocument
    {
        $disk = RequestWorkflow::proofDisk();
        $path = $file->storeAs("identity-documents/{$user->id}", Str::uuid().'.'.$file->extension(), $disk);

        if ($path === false) {
            throw ValidationException::withMessages(['idFile' => 'The file could not be saved. Please try again.']);
        }

        try {
            return DB::transaction(fn (): IdentityDocument => $user->identityDocuments()->create([
                'id_type' => $idType,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]));
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }
}
