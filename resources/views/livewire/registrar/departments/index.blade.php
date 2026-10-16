<div class="space-y-6">
    <x-page-header title="Departments" description="Colleges and offices that can be part of a request's signing workflow.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add department') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only placeholder="Search name or code" clearable />
        <flux:select wire:model.live="type" :label="__('Type')" label:sr-only>
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach ($types as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" :label="__('Status')" label:sr-only>
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$departments">
        <flux:table.columns>
            <flux:table.column>{{ __('Department') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Type') }}</flux:table.column>
            <flux:table.column class="max-lg:hidden">{{ __('Role in workflow') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Staff') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($departments as $department)
                <flux:table.row :key="$department->id">
                    <flux:table.cell class="whitespace-normal">
                        <div class="font-medium text-ink">{{ $department->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $department->code }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-sm:hidden">{{ $department->type->label() }}</flux:table.cell>
                    <flux:table.cell class="max-lg:hidden whitespace-normal text-zinc-600">{{ $department->description }}</flux:table.cell>
                    <flux:table.cell class="max-sm:hidden">{{ $department->staff_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$department->is_active ? 'green' : 'zinc'">{{ $department->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $department->name])" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $department->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item :icon="$department->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleStatus({{ $department->id }})" :wire:confirm="$department->is_active ? __('Deactivate :name? It will no longer be offered for new requests or new workflow steps.', ['name' => $department->name]) : null">
                                    {{ $department->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">{{ __('No departments match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="department-form" class="w-full md:w-[32rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->department ? __('Edit department') : __('Add department') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" required />
            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.code" :label="__('Code')" :description="__('Short code, e.g. CCS')" required />
                <flux:select wire:model="form.type" :label="__('Type')">
                    @foreach ($types as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:textarea wire:model="form.description" :label="__('Role in workflow')" badge="Optional" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
