<?php

namespace App\Listeners;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Events\RequestEventRecorded;
use App\Models\CredentialRequest;
use App\Services\TurnaroundPredictor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stores the model's turnaround prediction when a request is routed (REQ-08-04..06).
 *
 * A prediction is advice, not a status change, so it lives outside RequestWorkflow and runs
 * after the routing transaction has committed. Any failure is logged and never undoes routing.
 */
class RecordTurnaroundPrediction
{
    public function __construct(private readonly TurnaroundPredictor $predictor) {}

    public function handle(RequestEventRecorded $recorded): void
    {
        if ($recorded->event->type !== RequestEventType::Routed || ! $this->predictor->isAvailable()) {
            return;
        }

        try {
            /** @var CredentialRequest $request */
            $request = $recorded->event->credentialRequest()->with('credentialType')->firstOrFail();

            if ($request->routed_at === null || $request->due_at === null || $request->prediction()->exists()) {
                return;
            }

            $features = $this->predictor->features(
                $request->credentialType,
                $request->copies,
                $request->student_status,
                $request->routed_at,
                CredentialRequest::where('status', RequestStatus::InProgress)->whereKeyNot($request->id)->count(),
            );
            $days = (float) $this->predictor->predict($features);
            $readyAt = TurnaroundPredictor::readyAt($request->routed_at, $days);

            $request->prediction()->create([
                'model_version' => (string) $this->predictor->version(),
                'predicted_days' => $days,
                'predicted_ready_at' => $readyAt,
                'due_at' => $request->due_at,
                'at_risk' => $readyAt->greaterThan($request->due_at),
                'features' => $features,
            ]);
        } catch (Throwable $e) {
            Log::warning('Turnaround prediction skipped', [
                'credential_request_id' => $recorded->event->credential_request_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
