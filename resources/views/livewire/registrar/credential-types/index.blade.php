<div class="space-y-6">
    <x-page-header title="Credential Types" description="The credential master list: what requesters can ask for, its fee, processing period, requirements and route.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add credential type') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Credential') }}</flux:table.column>
            <flux:table.column>{{ __('Fee') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Processing') }}</flux:table.column>
            <flux:table.column>{{ __('Route') }}</flux:table.column>
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
                        @if ($type->steps->isEmpty())
                            <flux:badge size="sm" color="red" icon="exclamation-triangle">{{ __('No route') }}</flux:badge>
                        @else
                            <a href="{{ route('registrar.credential-types.workflow', $type) }}" class="text-highlight underline underline-offset-2 hover:no-underline" wire:navigate>{{ $type->routeSummary() }}</a>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-sm:hidden">
                        <flux:badge size="sm" :color="$type->is_active ? 'green' : 'zinc'">{{ $type->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $type->name])" />
                            <flux:menu>
                                <flux:menu.item icon="arrows-right-left" :href="route('registrar.credential-types.workflow', $type)" wire:navigate>{{ __('Edit route') }}</flux:menu.item>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $type->id }})">{{ __('Edit details') }}</flux:menu.item>
                                <flux:menu.item :icon="$type->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleStatus({{ $type->id }})" :wire:confirm="$type->is_active ? __('Deactivate :name? Requesters will no longer be able to request it.', ['name' => $type->name]) : null">
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
            <flux:textarea wire:model="form.description" :label="__('Description')" :description="__('Shown to requesters when they choose what to request.')" badge="Optional" rows="2" />

            <flux:switch wire:model.live="form.requires_payment" :label="__('Requires payment')" :description="__('Requesters pay on the UPHSL online payment portal and upload the receipt.')" />

            <div class="grid gap-6 sm:grid-cols-2">
                @if ($form->requires_payment)
                    <flux:input wire:model="form.fee" type="number" step="0.01" min="0" inputmode="decimal" :label="__('Fee per copy (₱)')" badge="Optional" />
                @endif
                <flux:input wire:model="form.processing_days" type="number" min="1" max="60" :label="__('Processing period')" :description="__('Working days from acceptance')" required />
            </div>

            <flux:textarea wire:model="form.requirements" :label="__('Documentary requirements')" :description="__('One per line. Shown before submitting and checked by the evaluator.')" badge="Optional" rows="4" />
            <flux:textarea wire:model="form.special_conditions" :label="__('Special conditions')" badge="Optional" rows="2" />
            <flux:switch wire:model="form.requires_receiving_institution" :label="__('Requires the receiving school')" :description="__('For transfer credentials: the requester must give the complete name of the receiving school.')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
