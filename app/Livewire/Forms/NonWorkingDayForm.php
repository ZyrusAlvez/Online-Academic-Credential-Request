<?php

namespace App\Livewire\Forms;

use App\Enums\NonWorkingDayKind;
use App\Models\NonWorkingDay;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

class NonWorkingDayForm extends Form
{
    public ?NonWorkingDay $day = null;

    public string $date = '';

    public string $name = '';

    public string $kind = 'holiday';

    public function edit(NonWorkingDay $day): void
    {
        $this->day = $day;
        $this->date = $day->date->format('Y-m-d');
        $this->name = $day->name;
        $this->kind = $day->kind->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('non_working_days', 'date')->ignore($this->day)],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(NonWorkingDayKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['date.unique' => 'This date is already in the calendar.'];
    }

    public function save(User $actor): NonWorkingDay
    {
        $validated = $this->validate();

        $day = $this->day ?? new NonWorkingDay;
        $day->fill($validated);
        $day->created_by ??= $actor->id;
        $day->save();

        $this->reset();

        return $day;
    }
}
