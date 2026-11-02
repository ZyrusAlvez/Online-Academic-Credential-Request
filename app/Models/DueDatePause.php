<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A formally approved pause of the processing period while the customer acts (spec 05). The
 * original due date never changes; closed pauses push the adjusted due date.
 *
 * @property string $reason
 * @property RequestStatus $resume_status
 * @property Carbon $started_at
 * @property ?Carbon $ended_at
 * @property ?int $working_days
 */
class DueDatePause extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resume_status' => RequestStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'working_days' => 'integer',
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
