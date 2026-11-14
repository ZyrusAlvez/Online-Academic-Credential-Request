<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPrivateFile;
use App\Models\RequestDocument;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestDocumentController extends Controller
{
    use ServesPrivateFile;

    public function show(RequestDocument $requestDocument): StreamedResponse
    {
        Gate::authorize('view', $requestDocument);

        return $this->serveFile($requestDocument->path, $requestDocument->original_name, $requestDocument->mime_type);
    }
}
