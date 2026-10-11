<div class="space-y-6">
    <x-page-header title="Credential Types" description="Documents students can request, and the offices each one must pass through.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add credential type') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Credential') }}</flux:table.column>
            <flux:table.column>{{ __('Payment') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Processing') }}</flux:table.column>
            <flux:table.column>{{ __('Workflow') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Status') }}</flux:table.column>
            <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($credentialTypes as $type)
                <flux:table.row :key="$type->id">
                    <flux:table.cell class="whitespace-normal">
                        <div class="font-medium text-ink">{{ $type->name }}</div>
                        @if ($type->description)
                            <div class="max-w-md text-xs text-zinc-500">{{ $type->description }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($type->requires_payment)
                            <flux:badge size="sm" color="amber">{{ $type->fee !== null ? '₱'.number_format((float) $type->fee, 2) : __('Paid') }}</flux:badge>
                        @else
                            <span class="text-zinc-500">{{ __('Free') }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ trans_choice(':count working day|:count working days', $type->processing_days) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($type->steps_count === 0)
                            <flux:badge size="sm" color="red" icon="exclamation-triangle">{{ __('No workflow') }}</flux:badge>
                        @else
                            <a href="{{ route('registrar.credential-types.workflow', $type) }}" class="text-highlight underline underline-offset-2 hover:no-underline" wire:navigate>
                                {{ trans_choice(':count step|:count steps', $type->steps_count) }}
                            </a>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-sm:hidden">
                        <flux:badge size="sm" :color="$type->is_active ? 'green' : 'zinc'">{{ $type->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $type->name])" />
                            <flux:menu>
                                <flux:menu.item icon="arrows-right-left" :href="route('registrar.credential-types.workflow', $type)" wire:navigate>{{ __('Edit workflow') }}</flux:menu.item>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $type->id }})">{{ __('Edit details') }}</flux:menu.item>
                                <flux:menu.item :icon="$type->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleStatus({{ $type->id }})">
                                    {{ $type->is_active ? __('Deactivate') : __('Activate') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">{{ __('No credential types yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="credential-type-form" class="w-full md:w-[34rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->credentialType ? __('Edit credential type') : __('Add credential type') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" required />
            <flux:textarea wire:model="form.description" :label="__('Description')" :description="__('Shown to students when they choose what to request.')" badge="Optional" rows="2" />

            <flux:switch wire:model.live="form.requires_payment" :label="__('Requires payment')" :description="__('Students pay on the UPHSL online payment portal and upload the receipt.')" />

            <div class="grid gap-6 sm:grid-cols-2">
                @if ($form->requires_payment)
                    <flux:input wire:model="form.fee" type="number" step="0.01" min="0" inputmode="decimal" :label="__('Fee (₱)')" badge="Optional" />
                @endif
                <flux:input wire:model="form.processing_days" type="number" min="1" max="60" :label="__('Processing days')" :description="__('Target turnaround')" required />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
