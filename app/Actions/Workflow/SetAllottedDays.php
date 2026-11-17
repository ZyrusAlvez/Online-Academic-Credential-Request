<?php

namespace App\Actions\Workflow;

use App\Models\CredentialType;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetAllottedDays
{
    public const MAX_DAYS = 60;

    /**
     * Save the Admin's split of the processing period among the route's offices (REQ-30-03).
     *
     * @param  array<int|string, mixed>  $days  working days keyed by workflow step id
     *
     * @throws ValidationException when a value is not 0–60, a step is missing, or the total is not the processing period
     */
    public function handle(CredentialType $type, array $days, string $errorKey = 'days'): void
    {
        DB::transaction(function () use ($type, $days, $errorKey): void {
            /** @var CredentialType $locked */
            $locked = CredentialType::whereKey($type->id)->lockForUpdate()->firstOrFail();
            $steps = $locked->steps()->get();
            $values = [];

            foreach ($steps as $step) {
                $value = $days[$step->id] ?? null;

                if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_DAYS]]) === false) {
                    throw ValidationException::withMessages([$errorKey => 'Enter 0 to '.self::MAX_DAYS.' working days for every office.']);
                }

                $values[$step->id] = (int) $value;
            }

            $total = array_sum($values);

            if ($total !== $locked->processing_days) {
                throw ValidationException::withMessages([$errorKey => "The offices' days add up to {$total}; they must add up to the processing period, {$locked->processing_days}."]);
            }

            // Eloquent updates, so the activity log records each office's new share.
            $steps->each(fn (WorkflowStep $step) => $step->update(['allotted_days' => $values[$step->id]]));
        });
    }
}
