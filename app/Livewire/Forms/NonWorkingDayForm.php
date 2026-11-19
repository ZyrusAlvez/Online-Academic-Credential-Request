<?php

namespace App\Livewire\Forms;

use App\Enums\NonWorkingDayKind;
use App\Models\NonWorkingDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class NonWorkingDayForm extends Form
{
    /** The longest period added at once (REQ-10-09). */
    public const MAX_PERIOD_DAYS = 60;

    public ?NonWorkingDay $day = null;

    public string $date = '';

    /** Optional last day of a period, when adding (REQ-10-09). */
    public string $until = '';

    public string $name = '';

    public string $kind = 'holiday';

    public function edit(NonWorkingDay $day): void
    {
        $this->day = $day;
        $this->date = $day->date->format('Y-m-d');
        $this->until = '';
        $this->name = $day->name;
        $this->kind = $day->kind->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $period = $this->day === null && filled($this->until);

        return [
            // A single date may appear only once; a period skips the dates already there (REQ-10-06, 09).
            'date' => ['required', 'date_format:Y-m-d', ...($period ? [] : [Rule::unique('non_working_days', 'date')->ignore($this->day)])],
            'until' => $this->day === null
                ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:date', function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && filled($this->date) && strtotime($this->date) !== false
                        && CarbonImmutable::parse($this->date)->diffInDays(CarbonImmutable::parse((string) $value)) >= self::MAX_PERIOD_DAYS) {
                        $fail('A period can be at most '.self::MAX_PERIOD_DAYS.' days.');
                    }
                }]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(NonWorkingDayKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'date.unique' => 'This date is already in the calendar.',
            'until.after_or_equal' => 'The period cannot end before it starts.',
        ];
    }

    /**
     * Save the day, or every day of the work week in the period that is not in the calendar yet
     * (REQ-10-06, 09). Returns the days saved, earliest first.
     *
     * @return Collection<int, NonWorkingDay>
     */
    public function save(User $actor): Collection
    {
        $validated = $this->validate();

        if ($this->day !== null || blank($validated['until'] ?? null)) {
            $day = $this->day ?? new NonWorkingDay;
            $day->fill(['date' => $validated['date'], 'name' => $validated['name'], 'kind' => $validated['kind']]);
            $day->created_by ??= $actor->id;
            $day->save();

            $this->reset();

            return collect([$day]);
        }

        $workWeek = array_map(intval(...), (array) config('uphsl.work_week', [1, 2, 3, 4, 5, 6]));
        $taken = NonWorkingDay::query()->whereBetween('date', [$validated['date'], $validated['until']])
            ->pluck('date')->map(fn ($date) => $date->format('Y-m-d'))->all();
        $dates = collect(CarbonPeriod::create($validated['date'], $validated['until']))
            ->filter(fn ($day): bool => in_array($day->dayOfWeekIso, $workWeek, true) && ! in_array($day->format('Y-m-d'), $taken, true))
            ->map(fn ($day): string => $day->format('Y-m-d'))
            ->values();

        if ($dates->isEmpty()) {
            throw ValidationException::withMessages(['form.until' => 'Every working day of this period is already in the calendar.']);
        }

        $saved = DB::transaction(fn (): Collection => $dates->map(function (string $date) use ($validated, $actor): NonWorkingDay {
            $day = new NonWorkingDay(['date' => $date, 'name' => $validated['name'], 'kind' => $validated['kind']]);
            $day->created_by = $actor->id;
            $day->save();

            return $day;
        }));

        $this->reset();

        return $saved;
    }
}
