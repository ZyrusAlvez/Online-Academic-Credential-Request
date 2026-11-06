<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Enums\ReviewStatus;
use App\Models\Concerns\LogsActivity;
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
     * The stored file's details and the reviewer (already the actor) add nothing to the log.
     *
     * @return list<string>
     */
    protected function activityIgnored(): array
    {
        return ['user_id', 'path', 'original_name', 'mime_type', 'size', 'reviewed_by', 'reviewed_at'];
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function shouldLogActivity(string $event, array $changes): bool
    {
        return $event === 'created' || array_key_exists('status', $changes);
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function activityDescription(string $event, array $changes, ?User $actor): string
    {
        if ($event === 'created') {
            return "Uploaded a valid ID ({$this->id_type})";
        }

        $owner = User::whereKey($this->user_id)->first();
        $verb = $this->status === ReviewStatus::Verified ? 'Verified' : 'Rejected';

        return "{$verb} the valid ID ({$this->id_type}) of ".($owner->name ?? 'a requester')
            .(filled($this->remarks) && $this->status === ReviewStatus::Rejected ? ": {$this->remarks}" : '');
    }
}
