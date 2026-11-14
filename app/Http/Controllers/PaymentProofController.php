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

        return $this->serveFile($paymentProof->path, $paymentProof->original_name, $paymentProof->mime_type);
    }
}
