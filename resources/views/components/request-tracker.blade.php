@props(['request', 'events' => collect()])

@php
    use App\Enums\RequestStatus as S;

    $status = $request->status;
    $accepted = $request->accepted_at !== null;
    $paid = $accepted || in_array($status, [S::ReadyForRelease, S::Released], true);
    $finalStage = in_array($status, [S::ForFinalApproval, S::ReadyForRelease, S::Released], true);
    $ready = in_array($status, [S::ReadyForRelease, S::Released], true);
    $closed = in_array($status, [S::Declined, S::Cancelled], true);
    $finalStageAt = $events->first(fn ($event) => $event->to_status === S::ForFinalApproval)?->created_at;

    // [title, description, icon, done | current | upcoming, date reached] for each milestone
    // (Proposed Workflow, sample customer portal).
    $milestones = [
        [__('Request submitted and evaluated'), __('Identity, academic records and documentary requirements checked.'), 'clipboard-document-check',
            $request->requirements_completed_at ? 'done' : ($closed ? 'upcoming' : 'current'), $request->requirements_completed_at],
        [__('Payment verified'), __('Payment confirmed by authorized personnel.'), 'banknotes',
            $paid ? 'done' : ($request->requirements_completed_at && ! $closed ? 'current' : 'upcoming'), $request->accepted_at],
        [__('Processing in progress'), $accepted && ! $finalStage ? __('Currently with :office.', ['office' => $request->whereabouts()]) : __('With the designated processing offices.'), 'building-office-2',
            $finalStage ? 'done' : ($accepted && ! $closed ? 'current' : 'upcoming'), $finalStageAt],
        [__('Final approval and quality check'), __('Remaining signatories and the Registrar\'s final check.'), 'shield-check',
            $ready ? 'done' : ($status === S::ForFinalApproval ? 'current' : 'upcoming'), $request->ready_at],
        [$status === S::Released ? __('Released') : __('Ready for release'), $status === S::Released ? __('Released :date.', ['date' => $request->released_at?->format('M j, Y')]) : __('You will be notified when the credential is available.'), 'academic-cap',
            $status === S::Released ? 'done' : ($ready ? 'current' : 'upcoming'), $request->released_at],
    ];

    $current = collect($milestones)->first(fn ($milestone) => $milestone[3] === 'current')
        ?? ($status === S::Released ? end($milestones) : null);

    // Every office the document goes through, done, current or to come (REQ-06-17).
    $offices = $closed ? [] : \App\Support\OfficeRoute::forRequest($request);
@endphp

<div {{ $attributes->class('space-y-5') }}>
    <ol class="flex flex-col sm:flex-row">
        @foreach ($milestones as $index => [$title, $text, $icon, $state, $date])
            @php $nextReached = ($milestones[$index + 1][3] ?? 'upcoming') !== 'upcoming'; @endphp
            <li @class(['relative flex gap-4 sm:flex-1 sm:flex-col sm:items-center sm:gap-3 sm:text-center', 'pb-6 sm:pb-0' => ! $loop->last])
                @if ($state === 'current') aria-current="step" @endif>
                @unless ($loop->last)
                    {{-- The line to the next milestone: down on phones, across from sm up. --}}
                    <span @class([
                        'absolute start-[calc(1.5rem-2px)] top-12 bottom-0 w-1',
                        'sm:start-[calc(50%+1.5rem)] sm:end-[calc(-50%+1.5rem)] sm:top-[calc(1.5rem-2px)] sm:bottom-auto sm:h-1 sm:w-auto',
                        'bg-green-600' => $nextReached,
                        'bg-zinc-200' => ! $nextReached,
                    ]) aria-hidden="true"></span>
                @endunless
                <span @class([
                    'relative flex size-12 shrink-0 items-center justify-center rounded-full border-4',
                    'border-green-600 bg-white text-green-700' => $state === 'done',
                    'border-primary bg-primary text-white ring-4 ring-primary-100' => $state === 'current',
                    'border-zinc-200 bg-white text-zinc-400' => $state === 'upcoming',
                ]) aria-hidden="true">
                    <flux:icon :name="$icon" class="size-6" />
                </span>
                <div class="pt-1 sm:px-1 sm:pt-0">
                    <p @class(['text-sm font-medium', 'text-ink' => $state !== 'upcoming', 'text-zinc-500' => $state === 'upcoming'])>
                        {{ $title }}
                        <span class="sr-only">({{ match ($state) { 'done' => __('done'), 'current' => __('current'), default => __('not yet') } }})</span>
                    </p>
                    @if ($state === 'done' && $date)
                        <p class="mt-0.5 text-xs text-zinc-600">
                            <time datetime="{{ $date->toIso8601String() }}">{{ $date->format('M j, Y') }}<span class="sm:block"><span class="sm:hidden"> · </span>{{ $date->format('g:i A') }}</span></time>
                        </p>
                    @elseif ($state === 'current')
                        <p class="mt-0.5 text-xs font-medium text-primary">{{ __('Current step') }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    @if ($current)
        <div class="rounded-lg bg-primary-50 px-4 py-3 text-sm">
            <p class="font-semibold text-primary">{{ $current[0] }}</p>
            <p class="text-zinc-700">{{ $current[1] }}</p>
        </div>
    @endif

    @if ($offices !== [])
        <div data-office-route>
            <h3 class="text-sm font-semibold text-ink">{{ __('Offices That Process It') }}</h3>
            <ol role="list" class="mt-3">
                @foreach ($offices as $index => $stop)
                    @php $nextReached = ($offices[$index + 1]['state'] ?? 'upcoming') !== 'upcoming'; @endphp
                    <li class="relative flex gap-3 pb-4 last:pb-0" data-office-state="{{ $stop['state'] }}"
                        @if ($stop['state'] === 'current') aria-current="step" @endif>
                        @unless ($loop->last)
                            <span @class([
                                'absolute top-7 bottom-1 left-3 w-0.5 -translate-x-1/2',
                                'bg-green-600' => $nextReached,
                                'bg-zinc-200' => ! $nextReached,
                            ]) aria-hidden="true"></span>
                        @endunless
                        <span @class([
                            'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                            'bg-green-600 text-white' => $stop['state'] === 'done',
                            'bg-primary text-white ring-4 ring-primary-100' => $stop['state'] === 'current',
                            'bg-zinc-200 text-zinc-600' => $stop['state'] === 'upcoming',
                        ]) aria-hidden="true">
                            @if ($stop['state'] === 'done')
                                <flux:icon.check variant="micro" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <div class="min-w-0 pt-0.5 text-sm">
                            <p @class(['text-ink' => $stop['state'] !== 'upcoming', 'text-zinc-600' => $stop['state'] === 'upcoming'])>
                                <span class="font-medium">{{ $stop['office'] }}</span>
                                <span class="text-zinc-600">({{ strtolower($stop['task']) }})</span>
                                <span class="sr-only">({{ match ($stop['state']) { 'done' => __('done'), 'current' => __('current'), default => __('not yet') } }})</span>
                            </p>
                            @if ($stop['state'] === 'done' && $stop['completed_at'])
                                <p class="text-xs text-zinc-600"><time datetime="{{ $stop['completed_at']->toIso8601String() }}">{{ $stop['completed_at']->format('M j, Y g:i A') }}</time></p>
                            @elseif ($stop['state'] === 'current')
                                <p class="text-xs font-medium text-primary">{{ __('Current office') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    <dl class="grid gap-4 border-t border-secondary/50 pt-4 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Processing period') }}</dt>
            <dd class="text-lg font-bold text-ink">{{ trans_choice(':count working day|:count working days', $request->credentialType->processing_days) }}</dd>
            <dd class="text-xs text-zinc-600">{{ __('Starts when your request is accepted.') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __('Expected release date') }}</dt>
            @if ($request->effective_due_at)
                <dd class="text-lg font-bold text-ink">{{ $request->effective_due_at->format('l, M j, Y') }}</dd>
                <dd class="text-xs text-zinc-600">
                    {{ __('Counted in working days from acceptance.') }}
                    @if ($request->adjusted_due_at)
                        {{ __('Originally :date.', ['date' => $request->due_at?->format('M j')]) }}
                    @endif
                </dd>
            @else
                <dd class="text-lg font-bold text-zinc-500">{{ __('Set on acceptance') }}</dd>
                <dd class="text-xs text-zinc-600">{{ __('Not yet accepted for processing.') }}</dd>
            @endif
        </div>
    </dl>
</div>
