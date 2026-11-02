<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A requirement found missing during evaluation (spec 04), resolved when the Registrar records
 * its physical submission or clearance.
 *
 * @property string $description
 * @property ?Carbon $resolved_at
 * @property ?string $resolution
 */
class RequestDeficiency extends Model
{
    protected $fillable = [
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
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
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
