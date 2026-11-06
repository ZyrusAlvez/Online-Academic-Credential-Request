<?php

namespace App\Models;

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The current result of one checklist part of a request (REQ-16-22). Written only by
 * App\Services\RequestWorkflow; the decision's ledger entry keeps the history.
 *
 * @property CheckItem $item
 * @property CheckResult $result
 * @property Carbon $checked_at
 */
class RequestCheck extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item' => CheckItem::class,
            'result' => CheckResult::class,
            'checked_at' => 'datetime',
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
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
