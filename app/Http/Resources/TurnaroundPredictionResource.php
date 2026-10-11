<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property array{credential_type: array{id: int, name: string}, predicted_days: float, routed_at: Carbon, predicted_ready_at: Carbon, due_at: Carbon, at_risk: bool, features: array<string, int>, model_version: string} $resource
 */
class TurnaroundPredictionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'credential_type' => $this->resource['credential_type'],
            'predicted_days' => $this->resource['predicted_days'],
            'routed_at' => $this->resource['routed_at']->toIso8601String(),
            'predicted_ready_at' => $this->resource['predicted_ready_at']->toIso8601String(),
            'due_at' => $this->resource['due_at']->toIso8601String(),
            'at_risk' => $this->resource['at_risk'],
            'features' => $this->resource['features'],
            'model_version' => $this->resource['model_version'],
        ];
    }
}
