<?php

namespace App\Http\Controllers;

use App\Models\RequestDocument;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestDocumentController extends Controller
{
    public function show(RequestDocument $requestDocument): StreamedResponse
    {
        Gate::authorize('view', $requestDocument);

        $disk = Storage::disk(RequestWorkflow::proofDisk());
        abort_unless($disk->exists($requestDocument->path), 404);

        return $disk->response($requestDocument->path, $requestDocument->original_name, [
            'Content-Type' => $requestDocument->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
