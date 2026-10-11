<?php

namespace App\Http\Controllers;

use App\Models\PaymentProof;
use App\Services\RequestWorkflow;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function show(PaymentProof $paymentProof): StreamedResponse
    {
        Gate::authorize('view', $paymentProof);

        $disk = Storage::disk(RequestWorkflow::proofDisk());
        abort_unless($disk->exists($paymentProof->path), 404);

        return $disk->response($paymentProof->path, $paymentProof->original_name, [
            'Content-Type' => $paymentProof->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
