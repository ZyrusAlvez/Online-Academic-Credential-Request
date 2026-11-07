<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Models\Concerns\LogsActivity;
use Database\Factories\IdentityDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A requester's uploaded valid ID (spec 01), stored privately on the receipts disk. The newest is
 * their saved ID; it is reviewed on each request that uses it (spec 15).
 *
 * @property int $user_id
 * @property string $id_type
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property Carbon $created_at
 */
class IdentityDocument extends Model
{
    /** @use HasFactory<IdentityDocumentFactory> */
    use HasFactory, LogsActivity;

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
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return Str::startsWith($this->mime_type, 'image/');
    }

    public function activityCategory(): ActivityCategory
    {
        return ActivityCategory::Identity;
    }

    public function activityNoun(): string
    {
        return 'valid ID';
    }

    public function activityLabel(): string
    {
        return $this->id_type;
    }

    /**
     * The stored file's details add nothing to the log.
     *
     * @return list<string>
     */
    protected function activityIgnored(): array
    {
        return ['user_id', 'path', 'original_name', 'mime_type', 'size'];
    }

    /**
     * Only the upload is logged here; the review is a ledger entry on the request (REQ-14-04).
     *
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function shouldLogActivity(string $event, array $changes): bool
    {
        return $event === 'created';
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function activityDescription(string $event, array $changes, ?User $actor): string
    {
        return "Uploaded a valid ID ({$this->id_type})";
    }
}
