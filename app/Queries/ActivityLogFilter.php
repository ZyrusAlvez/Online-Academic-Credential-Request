<?php

namespace App\Queries;

use App\Enums\ActivityCategory;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\CredentialRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The Logs page's search and filters (REQ-14-10, REQ-14-11).
 */
final class ActivityLogFilter
{
    /** Entries with no actor: failed sign-ins and scheduled jobs. */
    public const SYSTEM = 'system';

    /**
     * @param  string  $role  a Role value, 'system' or '' (anyone)
     * @param  string  $from  Y-m-d, a whole day in the app timezone
     * @param  string  $to  Y-m-d, a whole day in the app timezone
     */
    public function __construct(
        public string $search = '',
        public string $role = '',
        public string $category = '',
        public string $office = '',
        public string $from = '',
        public string $to = '',
    ) {}

    /**
     * @return Builder<ActivityLog>
     */
    public function query(): Builder
    {
        $term = trim($this->search);

        return ActivityLog::query()
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('reference_no', 'like', "%{$term}%")
                ->orWhere('actor_name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('actor', fn (Builder $q) => $q->where('email', 'like', "%{$term}%"))
                // The requester of a request entry, so "Juan" also finds what staff did on Juan's requests.
                ->orWhereHasMorph('subject', [CredentialRequest::class], fn (Builder $q) => $q
                    ->whereHas('student', fn (Builder $q) => $q
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])))))
            ->when($this->role === self::SYSTEM, fn (Builder $q) => $q->whereNull('actor_id'))
            ->when(Role::tryFrom($this->role), fn (Builder $q, Role $role) => $q->where('actor_role', $role))
            ->when(ActivityCategory::tryFrom($this->category), fn (Builder $q, ActivityCategory $category) => $q->where('category', $category))
            ->when($this->office !== '', fn (Builder $q) => $q->where('department_id', (int) $this->office))
            ->when(self::day($this->from), fn (Builder $q, Carbon $day) => $q->where('created_at', '>=', $day->startOfDay()))
            ->when(self::day($this->to), fn (Builder $q, Carbon $day) => $q->where('created_at', '<=', $day->endOfDay()))
            ->latest('id');
    }

    private static function day(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value) ?: null;
        } catch (Throwable) {
            return null;   // a hand-edited URL; ignore the filter rather than fail
        }
    }
}
