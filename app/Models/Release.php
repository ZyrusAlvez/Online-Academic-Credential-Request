<?php

namespace App\Models;

use App\Enums\RecipientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The release acknowledgment of a credential: who received it, when, and who released it
 * (spec 11).
 *
 * @property RecipientType $recipient_type
 * @property string $recipient_name
 * @property string $id_presented
 * @property bool $acknowledged
 * @property Carbon $released_at
 */
class Release extends Model
{
    protected $fillable = [
        'recipient_type',
        'request_representative_id',
        'recipient_name',
        'id_presented',
        'acknowledged',
        'released_by',
        'released_at',
        'remarks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient_type' => RecipientType::class,
            'acknowledged' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RequestRepresentative, $this>
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(RequestRepresentative::class, 'request_representative_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
