<?php

namespace App\Models\Concerns;

use App\Actions\Audit\RecordActivity;
use App\Enums\ActivityCategory;
use App\Models\Department;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Logs a model's additions, changes and removals to the activity log (REQ-14-02..04), with each
 * changed field's old and new value. Hidden fields (passwords, tokens) and timestamps are never
 * recorded (REQ-14-08). Nothing is logged while seeding or migrating: the console with nobody
 * signed in.
 *
 * The model names itself through activityCategory(), activityNoun() and activityLabel().
 */
trait LogsActivity
{
    abstract public function activityCategory(): ActivityCategory;

    /** What the record is, in a sentence: "credential type", "office", "account". */
    abstract public function activityNoun(): string;

    /** Which one it is: its name. */
    abstract public function activityLabel(): string;

    public static function bootLogsActivity(): void
    {
        static::created(fn (self $model) => $model->logActivity('created'));
        static::updated(fn (self $model) => $model->logActivity('updated'));
        static::deleted(fn (self $model) => $model->logActivity('deleted'));
    }

    /**
     * Fields left out of the log besides the hidden ones and the timestamps.
     *
     * @return list<string>
     */
    protected function activityIgnored(): array
    {
        return [];
    }

    protected function activityActor(string $event): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function activityDepartmentId(): ?int
    {
        return null;
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function shouldLogActivity(string $event, array $changes): bool
    {
        return $event !== 'updated' || $changes !== [];
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function activityDescription(string $event, array $changes, ?User $actor): string
    {
        $verb = match (true) {
            $event === 'created' => 'Added',
            $event === 'deleted' => 'Removed',
            array_keys($changes) === ['is_active'] => $this->getAttribute('is_active') ? 'Reactivated' : 'Deactivated',
            default => 'Updated',
        };

        return "{$verb} {$this->activityNoun()} “{$this->activityLabel()}”";
    }

    private function logActivity(string $event): void
    {
        $actor = $this->activityActor($event);

        if ($actor === null && app()->runningInConsole()) {
            return;
        }

        $changes = $event === 'deleted' ? [] : $this->activityChanges($event);

        if (! $this->shouldLogActivity($event, $changes)) {
            return;
        }

        app(RecordActivity::class)->handle(
            $actor,
            $this->activityCategory(),
            Str::snake(class_basename($this)).".{$event}",
            $this->activityDescription($event, $changes, $actor),
            subject: $this,
            departmentId: $this->activityDepartmentId(),
            changes: $changes,
        );
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    private function activityChanges(string $event): array
    {
        $skipped = [...$this->getHidden(), ...$this->activityIgnored(), $this->getKeyName(), 'created_at', 'updated_at'];
        $keys = array_keys($event === 'created' ? $this->getAttributes() : $this->getChanges());
        $changes = [];

        foreach (array_diff($keys, $skipped) as $key) {
            $old = $event === 'created' ? null : $this->activityValue($key, $this->getOriginal($key));
            $new = $this->activityValue($key, $this->getAttribute($key));

            if ($old !== $new) {
                $changes[$key] = [$old, $new];
            }
        }

        return $changes;
    }

    private function activityValue(string $key, mixed $value): ?string
    {
        if ($value !== null && in_array($key, ['department_id', 'college_id'], true)) {
            return Department::whereKey($value)->value('name') ?? (string) $value;
        }

        return match (true) {
            $value === null, $value === '', $value === [] => null,
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof BackedEnum => method_exists($value, 'label') ? $value->label() : (string) $value->value,
            $value instanceof DateTimeInterface => $value->format($value->format('His') === '000000' ? 'M j, Y' : 'M j, Y g:i A'),
            is_array($value) => implode('; ', array_map(fn ($item) => is_scalar($item) ? (string) $item : (string) json_encode($item), $value)),
            is_scalar($value) => Str::limit((string) $value, 200),
            default => (string) json_encode($value),
        };
    }
}
