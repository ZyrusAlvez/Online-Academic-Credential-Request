<?php

namespace App\Http\Controllers;

use App\Models\IdentityDocument;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdentityDocumentController extends Controller
{
    public function show(IdentityDocument $identityDocument): StreamedResponse
    {
        Gate::authorize('view', $identityDocument);

        $disk = Storage::disk(RequestWorkflow::proofDisk());
        abort_unless($disk->exists($identityDocument->path), 404);

        return $disk->response($identityDocument->path, $identityDocument->original_name, [
            'Content-Type' => $identityDocument->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
