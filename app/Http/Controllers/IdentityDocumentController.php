<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPrivateFile;
use App\Models\IdentityDocument;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdentityDocumentController extends Controller
{
    use ServesPrivateFile;

    public function show(IdentityDocument $identityDocument): StreamedResponse
    {
        Gate::authorize('view', $identityDocument);

        return $this->serveFile($identityDocument->path, $identityDocument->original_name, $identityDocument->mime_type);
    }
}
