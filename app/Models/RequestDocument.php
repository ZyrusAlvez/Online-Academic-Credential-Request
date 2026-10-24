<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A representative's SPA or valid ID, stored privately on the receipts disk (spec 11).
 *
 * @property int $credential_request_id
 * @property DocumentKind $kind
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 */
class RequestDocument extends Model
{
    protected $fillable = [
        'request_representative_id',
        'kind',
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
