<?php

namespace App\Livewire\Registrar\Calendar;

use App\Enums\NonWorkingDayKind;
use App\Livewire\Concerns\TrimsInput;
use App\Livewire\Forms\NonWorkingDayForm;
use App\Models\NonWorkingDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The working calendar: holidays and closures that due dates and office times skip (spec 10).
 */
#[Title('Working Calendar')]
class Index extends Component
{
    use TrimsInput;

    public NonWorkingDayForm $form;

    #[Url]
    public int $year;

    public function mount(): void
    {
        $this->authorize('viewAny', NonWorkingDay::class);

        $this->year ??= now()->year;
    }

    public function create(): void
    {
        $this->authorize('create', NonWorkingDay::class);

        $this->form->reset();
        $this->resetValidation();
        Flux::modal('day-form')->show();
    }

    public function edit(NonWorkingDay $day): void
    {
        $this->authorize('update', $day);

        $this->resetValidation();
        $this->form->edit($day);
        Flux::modal('day-form')->show();
    }

    public function save(): void
    {
        $this->form->day
            ? $this->authorize('update', $this->form->day)
            : $this->authorize('create', NonWorkingDay::class);

        $day = $this->form->save($this->user());

        $this->year = $day->date->year;
        Flux::modal('day-form')->close();
        $this->dispatch('notify', message: "{$day->name} saved.");
    }

    public function delete(NonWorkingDay $day): void
    {
        $this->authorize('delete', $day);

        $day->delete();

        $this->dispatch('notify', message: "{$day->name} removed.");
    }

    public function render(): View
    {
        $days = NonWorkingDay::query()
            ->whereYear('date', $this->year)
            ->orderBy('date')
            ->get();

        return view('livewire.registrar.calendar.index', [
            'days' => $days,
            'kinds' => NonWorkingDayKind::cases(),
            'workWeek' => $this->workWeekLabel(),
        ]);
    }

    /**
     * "Monday to Saturday" for a contiguous work week, otherwise the days listed.
     */
    private function workWeekLabel(): string
    {
        $days = array_map(intval(...), (array) config('uphsl.work_week'));
        sort($days);
        $name = fn (int $iso): string => CarbonImmutable::create(2026, 1, 4)->addDays($iso)->format('l');   // 2026-01-05 is a Monday

        if ($days === range($days[0], end($days))) {
            return $name($days[0]).' to '.$name(end($days));
        }

        return implode(', ', array_map($name, $days));
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
