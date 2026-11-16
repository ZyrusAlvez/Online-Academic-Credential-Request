<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPrivateFile;
use App\Models\PaymentProof;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    use ServesPrivateFile;

    public function show(PaymentProof $paymentProof): StreamedResponse
    {
        Gate::authorize('view', $paymentProof);
        // A walk-in payment has no file (REQ-26-06).
        abort_unless($paymentProof->hasFile(), 404);

        return $this->serveFile((string) $paymentProof->path, (string) $paymentProof->original_name, (string) $paymentProof->mime_type);
    }
}
