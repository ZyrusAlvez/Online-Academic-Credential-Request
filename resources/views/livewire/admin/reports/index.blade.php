<div class="space-y-6">
    <x-page-header title="Reports" description="Performance and monitoring reports." />

    {{-- Only the chosen report's filters (REQ-34-13). --}}
    @php($uses = $current->filters())
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="report" :label="__('Report')">
            @foreach ($kinds as $option)
                <flux:select.option :value="$option->value">{{ $option->report()->title() }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($kind === \App\Enums\ReportKind::Monthly)
            {{-- January to December of the chosen year (REQ-34-08). --}}
            <flux:select wire:model.live="year" :label="__('Year')">
                @foreach ($years as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:input wire:model.live="from" type="date" :label="__('From')" />
            <flux:input wire:model.live="to" type="date" :label="__('To')" />
        @endif
        @if (in_array('type', $uses, true))
            <flux:select wire:model.live="type" :label="__('Credential')">
                <flux:select.option value="">{{ __('All credentials') }}</flux:select.option>
                @foreach ($types as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        @if (in_array('evaluator', $uses, true))
            <flux:select wire:model.live="evaluator" :label="__('Evaluator')">
                <flux:select.option value="">{{ __('All evaluators') }}</flux:select.option>
                @foreach ($evaluators as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        @if (in_array('office', $uses, true))
            <flux:select wire:model.live="office" :label="__('Office')">
                <flux:select.option value="">{{ __('All offices') }}</flux:select.option>
                @foreach ($offices as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        @if ($current->shows() !== [])
            <flux:select wire:model.live="show" :label="__($current->showLabel())">
                @foreach ($current->shows() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <section aria-labelledby="report-heading" class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="report-heading" class="text-lg font-semibold text-primary">{{ $current->title() }}</h2>
                <p class="text-sm text-zinc-600">{{ $current->description() }}</p>
            </div>
            {{-- Every row of the current report, with its filters and sort (REQ-34-16). --}}
            <flux:button size="sm" icon="arrow-down-tray" wire:click="export">{{ __('Export CSV') }}</flux:button>
        </div>

        {{-- Click a column to sort by it; 50 rows per page (REQ-34-14). --}}
        <flux:table :paginate="$rows">
            <flux:table.columns>
                {{-- Everything centered except the first column, headers too (user, 2026-11-20). --}}
                @foreach ($current->columns() as $column)
                    @php($sorted = $sort === (string) $loop->index)
                    <flux:table.column :align="$loop->first ? 'start' : 'center'" @class(['text-center' => ! $loop->first]) data-column-header>
                        <button type="button" wire:click="sortBy({{ $loop->index }})" @class([
                            'group/sort flex w-full items-center gap-1',
                            'justify-start' => $loop->first,
                            'justify-center' => ! $loop->first,
                        ])>
                            <span>{{ $column }}</span>
                            <span @class(['text-zinc-400 group-hover/sort:text-zinc-800', 'opacity-0 group-hover/sort:opacity-100' => ! $sorted])>
                                @if ($sorted && $direction === 'asc')
                                    <flux:icon.chevron-up variant="micro" />
                                @else
                                    <flux:icon.chevron-down variant="micro" />
                                @endif
                            </span>
                        </button>
                    </flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row :key="$kind->value.'-'.$rows->currentPage().'-'.$loop->index">
                        @foreach ($row as $cell)
                            <flux:table.cell :align="$loop->first ? 'start' : 'center'" @class(['whitespace-normal', 'tabular-nums' => is_numeric($cell)])>{{ $cell ?? '—' }}</flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell :colspan="count($current->columns())" class="py-8 text-center text-zinc-500">{{ __('Nothing to report for these filters.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>
</div>
