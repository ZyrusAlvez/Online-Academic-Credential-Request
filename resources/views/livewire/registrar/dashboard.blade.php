<div class="space-y-8">
    <x-page-header title="Registrar Dashboard" description="Monitor every credential request and where it is waiting." />

    <section aria-labelledby="status-heading">
        <h2 id="status-heading" class="sr-only">{{ __('Requests by status') }}</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['href'] }}" wire:navigate class="rounded-xl border border-secondary/50 p-4 hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight">
                    <p class="text-sm text-zinc-600">{{ $tile['label'] }}</p>
                    <p class="mt-1 text-3xl font-bold text-primary tabular-nums">{{ $tile['value'] }}</p>
                </a>
            @endforeach
            <a href="{{ route('registrar.requests', ['status' => 'overdue']) }}" wire:navigate
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

        <dl class="mt-3 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-primary-50 p-4">
                <dt class="text-sm text-zinc-600">{{ __('Submitted this month') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $submittedThisMonth }}</dd>
            </div>
            <div class="rounded-xl bg-primary-50 p-4">
                <dt class="text-sm text-zinc-600">{{ __('Released this month') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $releasedThisMonth }}</dd>
            </div>
            <div class="rounded-xl bg-primary-50 p-4">
                <dt class="text-sm text-zinc-600">{{ __('Average processing time (released, last 30 days)') }}</dt>
                <dd class="text-2xl font-bold text-primary tabular-nums">{{ $avgProcessingDays === null ? '—' : trans_choice(':count working day|:count working days', $avgProcessingDays) }}</dd>
            </div>
        </dl>
    </section>

    <div class="grid gap-8 xl:grid-cols-2">
        <section aria-labelledby="waiting-heading" class="space-y-3">
            <div>
                <h2 id="waiting-heading" class="text-lg font-semibold text-primary">{{ __('Where requests are waiting') }}</h2>
                <p class="text-sm text-zinc-600">{{ __('Documents each office holds now, including those forwarded but not yet received.') }}</p>
            </div>

            @if ($waitingByOffice->isEmpty())
                <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('No requests are being processed.') }}</p>
            @else
                @php($max = max(1, $waitingByOffice->max('waiting')))
                <ul class="space-y-2.5">
                    @foreach ($waitingByOffice as $row)
                        <li>
                            <a href="{{ route('registrar.requests', ['status' => 'processing', 'office' => $row['id']]) }}" wire:navigate
                                class="group grid grid-cols-[minmax(0,10rem)_1fr_2.5rem] items-center gap-3 rounded-md p-1 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-highlight sm:grid-cols-[minmax(0,15rem)_1fr_2.5rem]"
                                title="{{ trans_choice(':count request|:count requests', $row['waiting']) }}{{ $row['inTransit'] ? ' · '.__(':n not yet received', ['n' => $row['inTransit']]) : '' }}">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm text-ink">{{ $row['name'] }}</span>
                                    @if ($row['inTransit'])
                                        <span class="block text-xs text-sky-800">{{ __(':n not yet received', ['n' => $row['inTransit']]) }}</span>
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
                <h2 id="overdue-heading" class="text-lg font-semibold text-primary">{{ __('Most overdue') }}</h2>
                <p class="text-sm text-zinc-600">{{ __('Being processed past their (adjusted) due date.') }}</p>
            </div>
            @if ($mostOverdue->isEmpty())
                <p class="rounded-xl border border-dashed border-secondary p-6 text-center text-sm text-zinc-600">{{ __('Nothing is overdue.') }}</p>
            @else
                <ul class="divide-y divide-secondary/40 rounded-xl border border-secondary/50">
                    @foreach ($mostOverdue as $request)
                        <li class="flex flex-wrap items-center justify-between gap-2 p-3">
                            <div class="min-w-0">
                                <a href="{{ route('registrar.requests.show', $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                                <p class="text-xs text-zinc-600">{{ $request->credentialType->name }} · {{ __('at :office', ['office' => $request->whereabouts()]) }}</p>
                            </div>
                            <flux:badge size="sm" color="red" icon="clock">{{ __('Due :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section aria-labelledby="office-queue-heading" class="space-y-3">
        <div>
            <h2 id="office-queue-heading" class="text-lg font-semibold text-primary">{{ __('Office of the Registrar queue') }}</h2>
            <p class="text-sm text-zinc-600">{{ __('Documents sent to your office for preparation, correction or the final quality check.') }}</p>
        </div>
        <x-visit-queue :visits="$officeQueue" link-route="registrar.requests.show" :empty="__('Nothing waiting for the Office of the Registrar.')" />
    </section>
</div>
