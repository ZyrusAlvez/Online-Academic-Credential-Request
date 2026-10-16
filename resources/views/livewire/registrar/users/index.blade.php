<div class="space-y-6">
    <x-page-header title="Accounts" description="Create staff accounts for departments and the Registrar, and activate or deactivate any account.">
        <x-slot:actions>
            <flux:button variant="primary" icon="user-plus" wire:click="create">{{ __('Add staff account') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only placeholder="Search name or email" clearable />
        <flux:select wire:model.live="role" :label="__('Role')" label:sr-only>
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach ($roles as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" :label="__('Status')" label:sr-only>
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$accounts">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Role') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Department') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($accounts as $account)
                <flux:table.row :key="$account->id">
                    <flux:table.cell class="whitespace-normal">
                        <div class="font-medium text-ink">{{ $account->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $account->email }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-sm:hidden">{{ $account->role->label() }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal">{{ $account->department?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$account->is_active ? 'green' : 'zinc'">{{ $account->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @canany(['update', 'setStatus'], $account)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $account->name])" />
                                <flux:menu>
                                    @can('update', $account)
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $account->id }})">{{ __('Edit') }}</flux:menu.item>
                                    @endcan
                                    @can('setStatus', $account)
                                        <flux:menu.item :icon="$account->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleStatus({{ $account->id }})" :wire:confirm="$account->is_active ? __('Deactivate :name? They will be logged out and cannot log in until reactivated.', ['name' => $account->name]) : null">
                                            {{ $account->is_active ? __('Deactivate') : __('Activate') }}
                                        </flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        @endcanany
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">{{ __('No accounts match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="staff-form" class="w-full md:w-[36rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->account ? __('Edit staff account') : __('Add staff account') }}</flux:heading>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.first_name" :label="__('First name')" required />
                <flux:input wire:model="form.last_name" :label="__('Last name')" required />
            </div>
            <flux:input wire:model="form.middle_name" :label="__('Middle name')" badge="Optional" />
            <flux:input wire:model="form.email" type="email" :label="__('Email')" required />

            <flux:radio.group wire:model.live="form.role" :label="__('Role')" variant="segmented">
                <flux:radio value="department" :label="__('Department staff')" />
                <flux:radio value="registrar" :label="__('Registrar')" />
            </flux:radio.group>

            @if ($form->role === 'department')
                <flux:select wire:model="form.department_id" :label="__('Department')">
                    <flux:select.option value="">{{ __('Choose a department…') }}</flux:select.option>
                    @foreach ($departments as $department)
                        <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input
                wire:model="form.password"
                type="password"
                autocomplete="new-password"
                :label="$form->account ? __('New password') : __('Initial password')"
                :badge="$form->account ? __('Leave blank to keep') : null"
                :description="__('At least 8 characters. Share it with the staff member; they can change it in Settings.')"
                viewable
                :required="! $form->account"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
