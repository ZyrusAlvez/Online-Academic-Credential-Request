<?php

namespace App\Models;

use App\Enums\CheckItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One part of a requester's details (User Identity or Academic Record Verification), verified on
 * one request (REQ-21-01). It counts while their saved details still equal that request's copy
 * (REQ-21-02, App\Support\VerifiedDetails). Written only by App\Services\RequestWorkflow.
 *
 * @property int $user_id
 * @property CheckItem $part
 * @property int $credential_request_id
 * @property int $verified_by
 * @property Carbon $verified_at
 */
class RequesterVerification extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'part' => CheckItem::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The request whose copy of the details was verified.
     *
     * @return BelongsTo<CredentialRequest, $this>
     */
    public function credentialRequest(): BelongsTo
    {
        return $this->belongsTo(CredentialRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
