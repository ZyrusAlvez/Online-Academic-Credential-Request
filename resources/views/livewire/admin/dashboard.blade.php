<div class="space-y-8">
    <x-page-header title="Dashboard" description="Every credential request at a glance." />

    <section aria-labelledby="status-heading">
        <h2 id="status-heading" class="sr-only">{{ __('Requests by Status') }}</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['href'] }}" wire:navigate class="rounded-xl border border-secondary/50 p-4 hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight">
                    <p class="text-sm text-zinc-600">{{ $tile['label'] }}</p>
                    <p class="mt-1 text-3xl font-bold text-primary tabular-nums">{{ $tile['value'] }}</p>
                </a>
            @endforeach
            <a href="{{ route('records', ['status' => 'overdue']) }}" wire:navigate
                @class([
                    'rounded-xl border p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight',
                    'border-red-700 bg-red-50 hover:bg-red-100' => $overdueCount > 0,
                    'border-secondary/50 hover:border-primary' => $overdueCount === 0,
                ])>
                <p @class(['flex items-center gap-1 text-sm', 'text-red-800' => $overdueCount > 0, 'text-zinc-600' => $overdueCount === 0])>
                    <flux:icon.clock variant="micro" /> {{ __('Overdue') }}
                </p>
                <p @class(['mt-1 text-3xl font-bold tabular-nums', 'text-red-800' => $overdueCount > 0, 'text-primary' => $overdueCount === 0])>{{ $overdueCount }}</p>
            </a>
        </div>

    </section>

    <section aria-labelledby="performance-heading" class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="performance-heading" class="text-lg font-semibold text-primary">{{ __('Performance') }}</h2>
                <p class="text-sm text-zinc-600">{{ __('Accepted requests due in the period, up to today.') }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <flux:input wire:model.live="from" type="date" size="sm" :label="__('From')" />
                <flux:input wire:model.live="to" type="date" size="sm" :label="__('To')" />
                <flux:button size="sm" icon="chart-bar" :href="$reportsUrl" wire:navigate>{{ __('Reports') }}</flux:button>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl bg-primary-50 p-4">
                <dt class="text-sm text-zinc-600">{{ __('Total requests') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->received }}</dd>
                <dd class="text-xs text-zinc-600">{{ __('Received in the period') }}</dd>
            </div>
            <div class="rounded-xl bg-primary-50 p-4">
                <dt class="text-sm text-zinc-600">{{ __('Released on time') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->onTime }} <span class="text-base font-semibold">/ {{ $metrics->onTimeRate() === null ? '—' : $metrics->onTimeRate().'%' }}</span></dd>
                <dd class="text-xs text-zinc-600">{{ trans_choice('of :count request due|of :count requests due', $metrics->eligible) }}</dd>
            </div>
            <div class="rounded-xl bg-primary-50 p-4">
                <dt class="text-sm text-zinc-600">{{ __('Overdue') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->overdue }} <span class="text-base font-semibold">/ {{ $metrics->overdueRate() === null ? '—' : $metrics->overdueRate().'%' }}</span></dd>
                <dd class="text-xs text-zinc-600">{{ __('Not released by the due date') }}</dd>
            </div>
            <div class="rounded-xl bg-primary-50 p-4">
                <dt class="text-sm text-zinc-600">{{ __('Average processing time') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $metrics->avgProcessingDays === null ? '—' : trans_choice(':count working day|:count working days', $metrics->avgProcessingDays) }}</dd>
                <dd class="text-xs text-zinc-600">{{ __('Acceptance to release') }}</dd>
            </div>
        </dl>
        <dl class="grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
            <div class="rounded-xl border border-secondary/50 p-3"><dt class="text-zinc-600">{{ __('Overdue workload (open now)') }}</dt><dd class="text-lg font-semibold text-ink tabular-nums">{{ $metrics->overdueWorkload }}</dd></div>
            <div class="rounded-xl border border-secondary/50 p-3"><dt class="text-zinc-600">{{ __('Returned for correction') }}</dt><dd class="text-lg font-semibold text-ink tabular-nums">{{ $metrics->returns }}{{ $metrics->returnRate() === null ? '' : ' ('.$metrics->returnRate().'% of office visits)' }}</dd></div>
            <div class="rounded-xl border border-secondary/50 p-3"><dt class="text-zinc-600">{{ __('Average time per office visit') }}</dt><dd class="text-lg font-semibold text-ink tabular-nums">{{ $metrics->avgOfficeDays === null ? '—' : trans_choice(':count working day|:count working days', $metrics->avgOfficeDays) }}</dd></div>
            <div class="rounded-xl border border-secondary/50 p-3"><dt class="text-zinc-600">{{ __('Average initial evaluation time') }}</dt><dd class="text-lg font-semibold text-ink tabular-nums">{{ $metrics->avgInitialEvaluationHours === null ? '—' : trans_choice(':count hour|:count hours', $metrics->avgInitialEvaluationHours) }}</dd></div>
        </dl>

        <div class="space-y-3">
            <div>
                <h3 class="font-semibold text-primary">{{ __('Processing Time by Office') }}</h3>
                <p class="text-sm text-zinc-600">{{ __('Average working days per visit, longest first.') }}</p>
            </div>
            @if ($metrics->byOffice->isEmpty())
                <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No office visits were completed in this period.') }}</p>
            @else
                @php($maxDays = max(1, $metrics->byOffice->max('days')))
                <ul class="space-y-2.5">
                    @foreach ($metrics->byOffice as $row)
                        <li class="grid grid-cols-[1fr_7rem] items-center gap-x-3 gap-y-1 sm:grid-cols-[minmax(0,15rem)_1fr_8rem]">
                            <span class="col-span-2 truncate text-sm text-ink sm:col-span-1">{{ $row['name'] }}</span>
                            <span class="h-3 rounded-e bg-primary/10" aria-hidden="true">
                                <span @class(['block h-3 rounded-e', 'bg-amber-600' => $loop->first && $metrics->byOffice->count() > 1, 'bg-primary' => ! ($loop->first && $metrics->byOffice->count() > 1)]) style="width: {{ round($row['days'] / $maxDays * 100, 1) }}%"></span>
                            </span>
                            <span class="text-end text-sm font-semibold text-ink tabular-nums">{{ trans_choice(':count working day|:count working days', $row['days']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <div class="grid gap-8 xl:grid-cols-2">
        <section aria-labelledby="waiting-heading" class="space-y-3">
            <div>
                <h2 id="waiting-heading" class="text-lg font-semibold text-primary">{{ __('Where Requests Are Waiting') }}</h2>
                <p class="text-sm text-zinc-600">{{ __('Documents each office holds now.') }}</p>
            </div>

            @if ($waitingByOffice->isEmpty())
                <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No requests are being processed.') }}</p>
            @else
                @php($max = max(1, $waitingByOffice->max('waiting')))
                <ul class="space-y-2.5">
                    @foreach ($waitingByOffice as $row)
                        <li>
                            <a href="{{ route('records', ['status' => 'processing', 'office' => $row['id']]) }}" wire:navigate
                                class="group grid grid-cols-[1fr_2.5rem] items-center gap-x-3 gap-y-1 rounded-md p-1 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-highlight sm:grid-cols-[minmax(0,15rem)_1fr_2.5rem]"
                                title="{{ trans_choice(':count request|:count requests', $row['waiting']) }}{{ $row['inTransit'] ? ' · '.__(':n forwarded, not yet received', ['n' => $row['inTransit']]) : '' }}">
                                <span class="col-span-2 min-w-0 sm:col-span-1">
                                    <span class="block truncate text-sm text-ink">{{ $row['name'] }}</span>
                                    @if ($row['inTransit'])
                                        <span class="block text-xs text-sky-800">{{ __(':n forwarded, not yet received', ['n' => $row['inTransit']]) }}</span>
                                    @endif
                                </span>
                                <span class="h-3 rounded-e bg-primary/10" aria-hidden="true">
                                    <span class="block h-3 rounded-e bg-primary group-hover:bg-primary-700" style="width: {{ round($row['waiting'] / $max * 100, 1) }}%"></span>
                                </span>
                                <span class="text-end text-sm font-semibold text-ink tabular-nums">{{ $row['waiting'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="overdue-heading" class="space-y-3">
            <div>
                <h2 id="overdue-heading" class="text-lg font-semibold text-primary">{{ __('Most Overdue') }}</h2>
                <p class="text-sm text-zinc-600">{{ __('Past their due date.') }}</p>
            </div>
            @if ($mostOverdue->isEmpty())
                <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('Nothing is overdue.') }}</p>
            @else
                <ul class="divide-y divide-secondary/40 rounded-xl border border-secondary/50">
                    @foreach ($mostOverdue as $request)
                        <li class="flex flex-wrap items-center justify-between gap-2 p-3">
                            <div class="min-w-0">
                                <a href="{{ route('records.show', $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                                <p class="text-xs text-zinc-600">{{ $request->credentialType->name }} · {{ __('at :office', ['office' => $request->staffWhereabouts()]) }}</p>
                            </div>
                            <flux:badge size="sm" color="red" icon="clock">{{ __('Due :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    {{-- No Office of the Registrar queue: the Registrar works those papers from In Progress (REQ-18-08). --}}
</div>
