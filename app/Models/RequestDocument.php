<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A representative's SPA or valid ID (spec 11), or a copy of a documentary requirement
 * (REQ-04-18), stored privately on the receipts disk.
 *
 * @property int $credential_request_id
 * @property DocumentKind $kind
 * @property ?string $requirement
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 */
class RequestDocument extends Model
{
    /** Files a requester may send for each documentary requirement (REQ-04-18). */
    public const MAX_PER_REQUIREMENT = 5;

    protected $fillable = [
        'request_representative_id',
        'kind',
        'requirement',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'size' => 'integer',
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
