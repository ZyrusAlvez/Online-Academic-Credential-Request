<?php

namespace App\Http\Controllers\Concerns;

use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a private upload (an ID, a proof of payment, an SPA) after the caller's policy check:
 * inline for the viewer, or as a download with `?download=1` (REQ-01-20, REQ-09-15).
 */
trait ServesPrivateFile
{
    protected function serveFile(string $path, string $name, string $mimeType): StreamedResponse
    {
        $disk = Storage::disk(RequestWorkflow::proofDisk());
        abort_unless($disk->exists($path), 404);

        $headers = [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return request()->boolean('download')
            ? $disk->download($path, $name, $headers)
            : $disk->response($path, $name, $headers);
    }
}
