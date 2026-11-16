<?php

namespace App\Models;

use App\Enums\RequestChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One application for one or more credentials (spec 27). Until acceptance it is evaluated and paid
 * as one, through its first credential (the lead); from acceptance each credential moves on its own.
 *
 * @property int $id
 * @property string $reference_no
 * @property ?int $user_id
 * @property RequestChannel $channel
 * @property ?Carbon $submitted_at
 */
class RequestApplication extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => RequestChannel::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference_no';
    }

    /**
     * The application's credentials, in their order (REQ-27-01).
     *
     * @return HasMany<CredentialRequest, $this>
     */
    public function credentialRequests(): HasMany
    {
        return $this->hasMany(CredentialRequest::class, 'application_id')->orderBy('item_no');
    }

    /**
     * The first credential, which carries the application until acceptance (REQ-27-03).
     *
     * @return HasOne<CredentialRequest, $this>
     */
    public function lead(): HasOne
    {
        return $this->hasOne(CredentialRequest::class, 'application_id')->where('item_no', 1);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
