<div class="space-y-6">
    <x-page-header title="Logs" description="Every action taken, newest first." />

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Transaction ID, name, email or action')" clearable class="md:col-span-2 xl:col-span-1" />
        <flux:select wire:model.live="role" :label="__('Role')">
            <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
            @foreach ($roles as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
            <flux:select.option value="{{ \App\Queries\ActivityLogFilter::SYSTEM }}">{{ __('System (no one signed in)') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="category" :label="__('Kind of action')">
            <flux:select.option value="">{{ __('All actions') }}</flux:select.option>
            @foreach ($categories as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="office" :label="__('Office')">
            <flux:select.option value="">{{ __('Any office') }}</flux:select.option>
            @foreach ($offices as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="from" type="date" :label="__('From')" />
        <flux:input wire:model.live="to" type="date" :label="__('To')" />
    </div>

    @if ($filtered)
        <div class="-mt-3">
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        </div>
    @endif

    <flux:table :paginate="$logs">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Who') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Transaction ID') }}</flux:table.column>
            <flux:table.column class="max-lg:hidden">{{ __('Office') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($logs as $log)
                <flux:table.row :key="$log->id" data-activity-log>
                    <flux:table.cell class="align-top">
                        <time datetime="{{ $log->created_at->toIso8601String() }}">
                            <div class="text-ink">{{ $log->created_at->format('M j, Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ $log->created_at->format('g:i:s A') }}</div>
                        </time>
                    </flux:table.cell>
                    <flux:table.cell class="align-top whitespace-normal">
                        <div class="font-medium text-ink">{{ $log->actor_name ?? __('System') }}</div>
                        <div class="text-xs text-zinc-500">{{ $log->actor_role?->label() ?? __('No one signed in') }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="align-top whitespace-normal">
                        <flux:badge size="sm" :color="$log->category->color()">{{ $log->category->label() }}</flux:badge>
                        <div class="mt-1 text-ink">{{ $log->description }}</div>

                        @if ($log->reference_no)
                            <div class="mt-1 text-xs md:hidden">
                                <a href="{{ route('records.show', $log->reference_no) }}" wire:navigate class="text-highlight underline underline-offset-2 hover:no-underline">{{ $log->reference_no }}</a>
                            </div>
                        @endif

                        @if ($log->changes)
                            <details class="mt-1 text-sm">
                                <summary class="cursor-pointer text-xs font-medium text-highlight">{{ __('Details') }}</summary>
                                <dl class="mt-2 space-y-1 rounded-md bg-zinc-50 p-2 text-xs">
                                    @foreach ($log->changes as $field => [$old, $new])
                                        <div>
                                            <dt class="font-medium text-zinc-700">{{ \Illuminate\Support\Str::headline($field) }}</dt>
                                            <dd class="text-zinc-600">
                                                @if ($old !== null)
                                                    <span class="line-through decoration-zinc-400">{{ $old }}</span>
                                                    <span aria-hidden="true">→</span>
                                                    <span class="sr-only">{{ __('changed to') }}</span>
                                                @endif
                                                <span class="text-ink">{{ $new ?? __('(empty)') }}</span>
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </details>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="align-top max-md:hidden">
                        @if ($log->reference_no)
                            <a href="{{ route('records.show', $log->reference_no) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $log->reference_no }}</a>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="align-top max-lg:hidden whitespace-normal text-zinc-700">{{ $log->department->name ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">{{ $filtered ? __('No entries match your filters.') : __('Nothing has been logged yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
