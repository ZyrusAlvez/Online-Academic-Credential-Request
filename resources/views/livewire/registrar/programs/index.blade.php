<div class="space-y-6">
    <x-page-header title="Programs" description="Programs and strands that requesters choose from on New Request and Settings → Profile, by department.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add program') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only placeholder="Search name" clearable />
        <flux:select wire:model.live="college" :label="__('Department')" label:sr-only>
            <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
            @foreach ($colleges as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" :label="__('Status')" label:sr-only>
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$programs">
        <flux:table.columns>
            <flux:table.column>{{ __('Program or strand') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Department') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($programs as $program)
                <flux:table.row :key="$program->id">
                    <flux:table.cell class="whitespace-normal">
                        <div class="font-medium text-ink">{{ $program->name }}</div>
                        <div class="text-xs text-zinc-500 sm:hidden">{{ $program->college->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-sm:hidden whitespace-normal">{{ $program->college->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$program->is_active ? 'green' : 'zinc'">{{ $program->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $program->name])" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $program->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item :icon="$program->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleStatus({{ $program->id }})" data-confirm-title="{{ __('Deactivate program') }}" data-confirm-label="{{ __('Deactivate') }}" data-confirm-variant="danger" :wire:confirm="$program->is_active ? __('Deactivate :name? Requesters will no longer be able to choose it.', ['name' => $program->name]) : null">
                                    {{ $program->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">{{ __('No programs match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="program-form" class="w-full md:w-[32rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->program ? __('Edit program') : __('Add program') }}</flux:heading>

            <flux:select wire:model="form.department_id" :label="__('Department')" required>
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach ($colleges as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="form.name" :label="__('Program or strand')" :placeholder="__('e.g. Bachelor of Science in Accountancy')" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
