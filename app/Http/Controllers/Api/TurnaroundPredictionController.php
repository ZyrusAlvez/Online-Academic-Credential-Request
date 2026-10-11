<?php

namespace App\Http\Controllers\Api;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PredictTurnaroundRequest;
use App\Http\Resources\TurnaroundPredictionResource;
use App\Models\CredentialType;
use App\Services\TurnaroundPredictor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * POST /api/v1/predictions/turnaround: predict a request profile's turnaround (REQ-08-07).
 * Nothing is stored; predictions for real requests are recorded when they are routed.
 */
class TurnaroundPredictionController extends Controller
{
    public function __invoke(PredictTurnaroundRequest $request, TurnaroundPredictor $predictor): JsonResponse|TurnaroundPredictionResource
    {
        if (! $predictor->isAvailable()) {
            return response()->json([
                'message' => 'The prediction model is not available.',
                'error_code' => 'model_unavailable',
            ], 503);
        }

        $type = CredentialType::findOrFail($request->integer('credential_type_id'));
        $routedAt = $request->filled('routed_at') ? Carbon::parse($request->string('routed_at')->toString()) : now();

        $features = $predictor->features(
            $type,
            $request->integer('copies'),
            StudentStatus::from($request->string('student_status')->toString()),
            $routedAt,
            $request->filled('open_workload') ? $request->integer('open_workload') : null,
        );
        $days = (float) $predictor->predict($features);
        $readyAt = TurnaroundPredictor::readyAt($routedAt, $days);
        $dueAt = TurnaroundPredictor::dueAt($routedAt, $type);
        $confidence = $predictor->confidence($days, $routedAt, $dueAt);

        return new TurnaroundPredictionResource([
            'credential_type' => ['id' => $type->id, 'name' => $type->name],
            'predicted_days' => $days,
            'routed_at' => $routedAt,
            'predicted_ready_at' => $readyAt,
            'due_at' => $dueAt,
            'at_risk' => $readyAt->greaterThan($dueAt),
            'confidence' => $confidence,
            'features' => $features,
            'model_version' => (string) $predictor->version(),
        ]);
    }
}
