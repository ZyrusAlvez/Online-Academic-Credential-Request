<?php

namespace App\Actions\Audit;

use App\Enums\ActivityCategory;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes one entry of the activity log (spec 14). The only place that creates ActivityLog rows.
 * The actor's name and role are copied in, so the entry keeps who they were at the time (REQ-14-06).
 */
final class RecordActivity
{
    /**
     * @param  ?User  $actor  who did it; null for a failed sign-in or a scheduled job
     * @param  ?array<string, array{0: ?string, 1: ?string}>  $changes  field => [old, new]
     */
    public function handle(
        ?User $actor,
        ActivityCategory $category,
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $referenceNo = null,
        ?int $departmentId = null,
        ?array $changes = null,
        ?CarbonInterface $at = null,
    ): ActivityLog {
        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role,
            'category' => $category,
            'action' => Str::limit($action, 60, ''),
            'description' => Str::limit($description, 497),
            'reference_no' => $referenceNo,
            'department_id' => $departmentId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $changes,
            // A backfilled entry has no request of its own; neither does a queue worker.
            'ip_address' => $at === null && (! app()->runningInConsole() || app()->runningUnitTests())
                ? request()->ip()
                : null,
            'created_at' => $at ?? now(),
        ]);
    }
}
