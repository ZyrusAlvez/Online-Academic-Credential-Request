<div class="space-y-6">
    <x-page-header title="Reports" description="The Registrar's performance and monitoring reports. Choose a report and filter it; export the full report as CSV.">
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" :href="$exportUrl">{{ __('Export CSV') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <flux:select wire:model.live="report" :label="__('Report')" class="xl:col-span-2">
            @foreach ($kinds as $option)
                <flux:select.option :value="$option->value">{{ $option->report()->title() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="from" type="date" :label="__('From')" />
        <flux:input wire:model.live="to" type="date" :label="__('To')" />
        <flux:select wire:model.live="type" :label="__('Credential')">
            <flux:select.option value="">{{ __('All credentials') }}</flux:select.option>
            @foreach ($types as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="evaluator" :label="__('Evaluator')">
            <flux:select.option value="">{{ __('All evaluators') }}</flux:select.option>
            @foreach ($evaluators as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="office" :label="__('Office')" class="md:col-span-3 xl:col-span-2">
            <flux:select.option value="">{{ __('All offices') }}</flux:select.option>
            @foreach ($offices as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <section aria-labelledby="report-heading" class="space-y-3">
        <div>
            <h2 id="report-heading" class="text-lg font-semibold text-primary">{{ $current->title() }}</h2>
            <p class="text-sm text-zinc-600">{{ $current->description() }}</p>
        </div>

        <flux:table>
            <flux:table.columns>
                @foreach ($current->columns() as $column)
                    <flux:table.column>{{ $column }}</flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse (array_slice($rows, 0, \App\Livewire\Registrar\Reports\Index::PAGE_ROWS) as $row)
                    <flux:table.row :key="$kind->value.'-'.$loop->index">
                        @foreach ($row as $cell)
                            <flux:table.cell @class(['whitespace-normal', 'tabular-nums' => is_numeric($cell)])>{{ $cell ?? '—' }}</flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell :colspan="count($current->columns())" class="py-8 text-center text-zinc-500">{{ __('Nothing to report for these filters.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if (count($rows) > \App\Livewire\Registrar\Reports\Index::PAGE_ROWS)
            <p class="text-sm text-zinc-600">{{ __('Showing the first :n rows. Export the CSV for the full report.', ['n' => \App\Livewire\Registrar\Reports\Index::PAGE_ROWS]) }}</p>
        @endif
    </section>
</div>
