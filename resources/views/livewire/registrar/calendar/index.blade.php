<div class="space-y-6">
    <x-page-header title="Working Calendar" :description="__('Holidays and closures that due dates and time per office skip. Working days are :days.', ['days' => $workWeek])">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add non-working day') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex items-center gap-2">
        <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="$set('year', {{ $year - 1 }})" :aria-label="__('Previous year')" />
        <flux:heading size="lg" class="tabular-nums">{{ $year }}</flux:heading>
        <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="$set('year', {{ $year + 1 }})" :aria-label="__('Next year')" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Kind') }}</flux:table.column>
            <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($days as $day)
                <flux:table.row :key="$day->id">
                    <flux:table.cell class="tabular-nums">
                        <div class="font-medium text-ink">{{ $day->date->format('M j') }}</div>
                        <div class="text-xs text-zinc-500">{{ $day->date->format('l') }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-normal">{{ $day->name }}</flux:table.cell>
                    <flux:table.cell class="max-sm:hidden">
                        <flux:badge size="sm" :color="$day->kind->color()">{{ $day->kind->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $day->name])" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $day->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $day->id }})" :wire:confirm="__('Remove :name? Due dates already set do not change.', ['name' => $day->name])">{{ __('Remove') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">{{ __('No non-working days in :year yet.', ['year' => $year]) }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="day-form" class="w-full md:w-[32rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->day ? __('Edit non-working day') : __('Add non-working day') }}</flux:heading>

            <flux:input wire:model="form.date" type="date" :label="__('Date')" required />
            <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('e.g. Eid\'l Fitr, Typhoon closure')" required />
            <flux:select wire:model="form.kind" :label="__('Kind')">
                @foreach ($kinds as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:text>{{ __('Due dates already set are not changed; the day counts for due dates set from now on.') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
