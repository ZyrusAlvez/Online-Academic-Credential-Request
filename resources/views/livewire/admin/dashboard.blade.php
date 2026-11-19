<div class="space-y-8">
    <x-page-header title="Dashboard">
        <x-slot:actions>
            <div class="flex flex-wrap items-end gap-2">
                <flux:input wire:model.live="from" type="date" size="sm" :label="__('From')" />
                <flux:input wire:model.live="to" type="date" size="sm" :label="__('To')" />
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Every number opens the report rows behind it (REQ-34-01..03, 09). --}}
    @php($report = fn (string $kind, array $params = []): string => route('admin.reports', [...$period, 'report' => $kind, ...$params]))
    @php($linkClass = 'font-semibold text-highlight underline underline-offset-2 hover:no-underline')

    {{-- The three indicators (REQ-34-01). --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <a href="{{ $report('transactions') }}" wire:navigate class="block cursor-pointer rounded-xl bg-primary-50 p-4 ring-primary/40 transition hover:ring-2" data-tile="total">
            <dl>
                <dt class="text-sm text-zinc-600">{{ __('Total requests') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->received }}</dd>
            </dl>
        </a>
        <a href="{{ $report('transactions', ['show' => 'on_time']) }}" wire:navigate class="block cursor-pointer rounded-xl bg-primary-50 p-4 ring-primary/40 transition hover:ring-2" data-tile="on-time">
            <dl>
                <dt class="text-sm text-zinc-600">{{ __('Ready on time') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->onTime }} <span class="text-base font-semibold">/ {{ $metrics->onTimeRate() === null ? '—' : $metrics->onTimeRate().'%' }}</span></dd>
            </dl>
        </a>
        <a href="{{ $report('delay') }}" wire:navigate @class(['block cursor-pointer rounded-xl p-4 transition hover:ring-2', 'bg-red-50 ring-red-300' => $metrics->overdue > 0, 'bg-primary-50 ring-primary/40' => $metrics->overdue === 0]) data-tile="overdue">
            <dl>
                <dt @class(['text-sm', 'text-red-800' => $metrics->overdue > 0, 'text-zinc-600' => $metrics->overdue === 0])>{{ __('Overdue') }}</dt>
                <dd @class(['text-2xl font-bold tabular-nums', 'text-red-800' => $metrics->overdue > 0, 'text-primary' => $metrics->overdue === 0])>{{ $metrics->overdue }} <span class="text-base font-semibold">/ {{ $metrics->overdueRate() === null ? '—' : $metrics->overdueRate().'%' }}</span></dd>
            </dl>
        </a>
    </div>

    {{-- Where the time goes, longest first (REQ-34-02). --}}
    <section aria-labelledby="offices-heading" class="space-y-3">
        <h2 id="offices-heading" class="text-lg font-semibold text-primary">{{ __('Processing Time by Office') }}</h2>

        @if ($offices->isEmpty())
            <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No documents in the offices in this period.') }}</p>
        @else
            @php($maxDays = max(1, (float) $offices->max('days')))
            <div class="overflow-x-auto rounded-xl border border-secondary/50">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="bg-primary-50 text-start text-zinc-600">
                        <tr>
                            <th scope="col" class="p-3 text-start font-medium">{{ __('Office') }}</th>
                            <th scope="col" class="p-3 text-center font-medium">{{ __('Avg per document') }}</th>
                            <th scope="col" class="p-3 text-center font-medium">{{ __('Done') }}</th>
                            <th scope="col" class="p-3 text-center font-medium">{{ __('Pending') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/40">
                        @foreach ($offices as $office)
                            @php($documents = fn (string $show = ''): string => $report('office-documents', array_filter(['office' => $office['id'], 'show' => $show])))
                            <tr>
                                <th scope="row" class="p-3 text-start font-medium">
                                    <a href="{{ $documents() }}" wire:navigate class="text-ink underline-offset-2 hover:text-highlight hover:underline">{{ $office['name'] }}</a>
                                </th>
                                <td class="p-3 text-center">
                                    @if ($office['days'] === null)
                                        <span class="text-zinc-500">—</span>
                                    @else
                                        {{-- Every bar's track and label have the same width, so the bars compare (user, 2026-11-20). --}}
                                        <span class="mx-auto flex w-fit items-center gap-3">
                                            <span class="h-3 w-32 shrink-0 rounded-e bg-primary/10" aria-hidden="true" data-bar-track>
                                                <span class="block h-3 rounded-e bg-primary" style="width: {{ round($office['days'] / $maxDays * 100, 1) }}%"></span>
                                            </span>
                                            <a href="{{ $documents('finished') }}" wire:navigate class="w-32 shrink-0 text-start tabular-nums {{ $linkClass }}">{{ trans_choice(':count working day|:count working days', $office['days']) }}</a>
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 text-center tabular-nums">
                                    @if ($office['done'] > 0)
                                        <a href="{{ $documents('done') }}" wire:navigate class="{{ $linkClass }}">{{ $office['done'] }}</a>
                                    @else
                                        0
                                    @endif
                                </td>
                                <td class="p-3 text-center tabular-nums">
                                    @if ($office['pending'] > 0)
                                        <a href="{{ $documents('pending') }}" wire:navigate class="{{ $linkClass }}">{{ $office['pending'] }}</a>
                                    @else
                                        0
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Average processing time per credential, longest first (REQ-34-09). --}}
    <section aria-labelledby="credentials-heading" class="space-y-3">
        <h2 id="credentials-heading" class="text-lg font-semibold text-primary">{{ __('Processing Time by Credential') }}</h2>

        @if ($credentials->isEmpty())
            <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No credentials ready for release in this period.') }}</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-secondary/50">
                <table class="w-full min-w-[30rem] text-sm">
                    <thead class="bg-primary-50 text-start text-zinc-600">
                        <tr>
                            <th scope="col" class="p-3 text-start font-medium">{{ __('Credential') }}</th>
                            <th scope="col" class="p-3 text-center font-medium">{{ __('Ready') }}</th>
                            <th scope="col" class="p-3 text-center font-medium">{{ __('Avg processing') }}</th>
                            <th scope="col" class="p-3 text-center font-medium">{{ __('Standard') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/40">
                        @foreach ($credentials as $credential)
                            @php($over = $credential['days'] > $credential['standard'])
                            @php($ready = $report('transactions', ['type' => $credential['id'], 'show' => 'ready']))
                            <tr>
                                <th scope="row" class="p-3 text-start font-medium">
                                    <a href="{{ $ready }}" wire:navigate class="text-ink underline-offset-2 hover:text-highlight hover:underline">{{ $credential['name'] }}</a>
                                </th>
                                <td class="p-3 text-center tabular-nums">
                                    <a href="{{ $ready }}" wire:navigate class="{{ $linkClass }}">{{ $credential['ready'] }}</a>
                                </td>
                                <td class="p-3 text-center tabular-nums" @if ($over) data-over-standard @endif>
                                    <a href="{{ $ready }}" wire:navigate @class(['font-semibold underline underline-offset-2 hover:no-underline', 'text-red-700' => $over, 'text-highlight' => ! $over])>{{ trans_choice(':count working day|:count working days', $credential['days']) }}</a>
                                </td>
                                <td class="p-3 text-center tabular-nums text-zinc-600">{{ trans_choice(':count working day|:count working days', $credential['standard']) }}</td>
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
            <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" :href="$report('delay')" wire:navigate>{{ __('Delay Analysis') }}</flux:button>
        </div>

        @if ($causes->sum('count') === 0)
            <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('Nothing is overdue.') }}</p>
        @else
            <ul class="divide-y divide-secondary/40 rounded-xl border border-secondary/50">
                @foreach ($causes as $cause)
                    @php($byCause = $report('delay', ['show' => $cause['value']]))
                    <li class="flex items-center justify-between gap-3 p-3 text-sm">
                        <span class="flex items-center gap-2 text-ink">
                            <flux:tooltip :content="__($cause['scope'])" position="top">
                                <button type="button" class="text-zinc-500 hover:text-primary" aria-label="{{ __('About :cause', ['cause' => $cause['label']]) }}" data-cause-scope="{{ $cause['scope'] }}">
                                    <flux:icon.information-circle variant="mini" class="size-4" />
                                </button>
                            </flux:tooltip>
                            @if ($cause['count'] > 0)
                                <a href="{{ $byCause }}" wire:navigate class="underline-offset-2 hover:text-highlight hover:underline">{{ $cause['label'] }}</a>
                            @else
                                {{ $cause['label'] }}
                            @endif
                        </span>
                        @if ($cause['count'] > 0)
                            <a href="{{ $byCause }}" wire:navigate class="tabular-nums {{ $linkClass }}">{{ $cause['count'] }}</a>
                        @else
                            <span class="font-semibold text-ink tabular-nums">0</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    {{-- No Office of the Registrar queue: the Registrar works those papers from Outgoing (REQ-18-08). --}}
</div>
