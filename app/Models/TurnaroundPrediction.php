<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The model's turnaround prediction for a routed request (spec 08). Insert-only.
 *
 * @property int $credential_request_id
 * @property string $model_version
 * @property string $predicted_days
 * @property Carbon $predicted_ready_at
 * @property Carbon $due_at
 * @property bool $at_risk
 * @property array<string, int> $features
 * @property Carbon $created_at
 */
class TurnaroundPrediction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'model_version',
        'predicted_days',
        'predicted_ready_at',
        'due_at',
        'at_risk',
        'features',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'predicted_days' => 'decimal:2',
            'predicted_ready_at' => 'datetime',
            'due_at' => 'datetime',
            'at_risk' => 'boolean',
            'features' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CredentialRequest, $this>
     */
    public function credentialRequest(): BelongsTo
    {
        return $this->belongsTo(CredentialRequest::class);
    }
}
