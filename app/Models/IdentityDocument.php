<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\IdentityDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A requester's uploaded valid ID (spec 01). Stored privately on the receipts disk.
 *
 * @property string $id_type
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property ReviewStatus $status
 * @property ?Carbon $reviewed_at
 * @property ?string $remarks
 */
class IdentityDocument extends Model
{
    /** @use HasFactory<IdentityDocumentFactory> */
    use HasFactory;

    /** Accepted kinds of valid ID, shown as choices on upload. */
    public const ID_TYPES = [
        'PhilSys National ID',
        'Passport',
        "Driver's license",
        'UMID',
        'PRC ID',
        'Postal ID',
        "Voter's ID",
        'School ID',
        'Other government-issued ID',
    ];

    protected $fillable = [
        'id_type',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'size' => 'integer',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isImage(): bool
    {
        return Str::startsWith($this->mime_type, 'image/');
    }
}
