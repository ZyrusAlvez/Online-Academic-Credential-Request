<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property PaymentStatus $status
 * @property ?string $amount
 * @property ?Carbon $paid_on
 * @property ?string $official_receipt_no
 * @property int $size
 * @property ?Carbon $reviewed_at
 * @property Carbon $created_at
 */
class PaymentProof extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'uploaded_by',
        'path',
        'original_name',
        'mime_type',
        'size',
        'reference_no',
        'amount',
        'paid_on',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'size' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
