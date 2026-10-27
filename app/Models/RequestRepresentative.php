<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person authorized by the document owner to transact for them, recorded apart from the
 * owner's account (spec 11).
 *
 * @property int $credential_request_id
 * @property string $full_name
 * @property ?string $relationship
 * @property ?string $contact_number
 * @property ReviewStatus $status
 * @property ?Carbon $reviewed_at
 * @property ?string $remarks
 */
class RequestRepresentative extends Model
{
    protected $fillable = [
        'full_name',
        'relationship',
        'contact_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CredentialRequest, $this>
     */
    public function credentialRequest(): BelongsTo
    {
        return $this->belongsTo(CredentialRequest::class);
    }

    /**
     * @return HasMany<RequestDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
