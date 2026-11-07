<div class="space-y-6">
    <x-page-header title="Purposes" description="Purposes that requesters choose from on New Request.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add purpose') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only placeholder="Search name" clearable />
        <flux:select wire:model.live="status" :label="__('Status')" label:sr-only>
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$purposes">
        <flux:table.columns>
            <flux:table.column>{{ __('Purpose') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($purposes as $purpose)
                <flux:table.row :key="$purpose->id">
                    <flux:table.cell class="whitespace-normal font-medium text-ink">{{ $purpose->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$purpose->is_active ? 'green' : 'zinc'">{{ $purpose->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $purpose->name])" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $purpose->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item :icon="$purpose->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleStatus({{ $purpose->id }})" data-confirm-title="{{ __('Deactivate purpose') }}" data-confirm-label="{{ __('Deactivate') }}" data-confirm-variant="danger" :wire:confirm="$purpose->is_active ? __('Deactivate :name? Requesters will no longer be able to choose it.', ['name' => $purpose->name]) : null">
                                    {{ $purpose->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">{{ __('No purposes match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="purpose-form" class="w-full md:w-[32rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->purpose ? __('Edit purpose') : __('Add purpose') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Purpose')" :description="__('Renaming a purpose doesn\'t change requests already made.')" :placeholder="__('e.g. Credit evaluation abroad')" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
