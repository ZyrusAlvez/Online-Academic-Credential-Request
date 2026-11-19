<div class="space-y-8">
    <x-page-header title="Dashboard">
        <x-slot:actions>
            <div class="flex flex-wrap items-end gap-2">
                <flux:input wire:model.live="from" type="date" size="sm" :label="__('From')" />
                <flux:input wire:model.live="to" type="date" size="sm" :label="__('To')" />
                <flux:button size="sm" icon="chart-bar" :href="$reportsUrl" wire:navigate>{{ __('Reports') }}</flux:button>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- The four indicators (REQ-34-01). --}}
    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl bg-primary-50 p-4">
            <dt class="text-sm text-zinc-600">{{ __('Total requests') }}</dt>
            <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->received }}</dd>
        </div>
        <div class="rounded-xl bg-primary-50 p-4">
            <dt class="text-sm text-zinc-600">{{ __('Released on time') }}</dt>
            <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->onTime }} <span class="text-base font-semibold">/ {{ $metrics->onTimeRate() === null ? '—' : $metrics->onTimeRate().'%' }}</span></dd>
        </div>
        <div @class(['rounded-xl p-4', 'bg-red-50' => $metrics->overdue > 0, 'bg-primary-50' => $metrics->overdue === 0])>
            <dt @class(['text-sm', 'text-red-800' => $metrics->overdue > 0, 'text-zinc-600' => $metrics->overdue === 0])>{{ __('Overdue') }}</dt>
            <dd @class(['text-2xl font-bold tabular-nums', 'text-red-800' => $metrics->overdue > 0, 'text-primary' => $metrics->overdue === 0])>{{ $metrics->overdue }} <span class="text-base font-semibold">/ {{ $metrics->overdueRate() === null ? '—' : $metrics->overdueRate().'%' }}</span></dd>
        </div>
        <div class="rounded-xl bg-primary-50 p-4">
            <dt class="text-sm text-zinc-600">{{ __('Average processing time') }}</dt>
            <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->avgProcessingDays === null ? '—' : trans_choice(':count working day|:count working days', $metrics->avgProcessingDays) }}</dd>
        </div>
    </dl>

    {{-- Where the time goes, longest first (REQ-34-02). --}}
    <section aria-labelledby="offices-heading" class="space-y-3">
        <h2 id="offices-heading" class="text-lg font-semibold text-primary">{{ __('Processing Time by Office') }}</h2>

        @if ($offices->isEmpty())
            <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No documents in the offices in this period.') }}</p>
        @else
            @php($maxDays = max(1, (float) $offices->max('days')))
            @php($slowest = $offices->count() > 1 && $offices->first()['days'] !== null ? $offices->first()['id'] : null)
            <div class="overflow-x-auto rounded-xl border border-secondary/50">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="bg-primary-50 text-start text-zinc-600">
                        <tr>
                            <th scope="col" class="p-3 text-start font-medium">{{ __('Office') }}</th>
                            <th scope="col" class="p-3 text-start font-medium">{{ __('Avg per document') }}</th>
                            <th scope="col" class="p-3 text-end font-medium">{{ __('Done') }}</th>
                            <th scope="col" class="p-3 text-end font-medium">{{ __('Pending now') }}</th>
                            <th scope="col" class="p-3 text-end font-medium">{{ __('Oldest pending') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/40">
                        @foreach ($offices as $office)
                            <tr @class(['bg-amber-50' => $office['id'] === $slowest]) @if ($office['id'] === $slowest) data-slowest @endif>
                                <th scope="row" class="p-3 text-start font-medium text-ink">{{ $office['name'] }}</th>
                                <td class="p-3">
                                    @if ($office['days'] === null)
                                        <span class="text-zinc-500">—</span>
                                    @else
                                        <span class="flex items-center gap-3">
                                            <span class="h-3 w-full max-w-48 rounded-e bg-primary/10" aria-hidden="true">
                                                <span @class(['block h-3 rounded-e', 'bg-amber-600' => $office['id'] === $slowest, 'bg-primary' => $office['id'] !== $slowest]) style="width: {{ round($office['days'] / $maxDays * 100, 1) }}%"></span>
                                            </span>
                                            <span class="shrink-0 font-semibold text-ink tabular-nums">{{ trans_choice(':count working day|:count working days', $office['days']) }}</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 text-end tabular-nums">{{ $office['done'] }}</td>
                                <td class="p-3 text-end tabular-nums">
                                    @if ($office['pending'] > 0)
                                        <a href="{{ route('records', ['status' => 'processing', 'office' => $office['id']]) }}" wire:navigate class="font-semibold text-highlight underline underline-offset-2 hover:no-underline">{{ $office['pending'] }}</a>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="p-3 text-end tabular-nums">{{ $office['oldest'] === null ? '—' : trans_choice(':count working day|:count working days', $office['oldest']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Why requests are late (REQ-34-03). --}}
    <section aria-labelledby="causes-heading" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="causes-heading" class="text-lg font-semibold text-primary">{{ __('Causes of Delay') }}</h2>
            <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" :href="$delayUrl" wire:navigate>{{ __('Delay Analysis') }}</flux:button>
        </div>

        @if ($causes->isEmpty())
            <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('Nothing is overdue.') }}</p>
        @else
            <ul class="divide-y divide-secondary/40 rounded-xl border border-secondary/50">
                @foreach ($causes as $cause => $count)
                    <li class="flex items-center justify-between gap-3 p-3 text-sm">
                        <span class="text-ink">{{ $cause }}</span>
                        <span class="font-semibold text-ink tabular-nums">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    {{-- No Office of the Registrar queue: the Registrar works those papers from Outgoing (REQ-18-08). --}}
</div>
