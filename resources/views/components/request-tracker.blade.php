@props(['request', 'events' => collect()])

@php
    use App\Enums\RequestStatus as S;

    $status = $request->status;
    $paid = $request->accepted_at !== null || in_array($status, [S::ReadyForRelease, S::Released], true);
    $ready = in_array($status, [S::ReadyForRelease, S::Released], true);
    $closed = in_array($status, [S::Declined, S::Cancelled], true);
    $finalCheck = \App\Enums\RouteTask::FinalCheck->label();

    // One step bar (Proposed Workflow, sample customer portal): submitted, payment, then every
    // office the document goes through (REQ-06-17), then ready for release.
    $milestones = [
        ['title' => __('Request submitted and evaluated'), 'task' => null, 'text' => __('Identity, academic records and documentary requirements checked.'), 'icon' => 'clipboard-document-check',
            'state' => $request->requirements_completed_at ? 'done' : ($closed ? 'upcoming' : 'current'), 'date' => $request->requirements_completed_at],
        ['title' => __('Payment verified'), 'task' => null, 'text' => __('Payment confirmed by authorized personnel.'), 'icon' => 'banknotes',
            'state' => $paid ? 'done' : ($request->requirements_completed_at && ! $closed ? 'current' : 'upcoming'), 'date' => $request->accepted_at],
        ...array_map(fn (array $stop): array => [
            // Where the paper physically is, which can differ from the step's office (REQ-17-18).
            'title' => $stop['office'], 'task' => $stop['task'], 'text' => __('Currently with :office.', ['office' => $request->whereabouts()]),
            'icon' => $stop['task'] === $finalCheck ? 'shield-check' : 'building-office-2',
            'state' => $closed && $stop['state'] === 'current' ? 'upcoming' : $stop['state'], 'date' => $stop['completed_at'],
        ], \App\Support\OfficeRoute::forRequest($request)),
        ['title' => $status === S::Released ? __('Released') : __('Ready for release'), 'task' => null, 'text' => $status === S::Released ? __('Released :date.', ['date' => $request->released_at?->format('M j, Y')]) : __('You will be notified when the credential is available.'), 'icon' => 'academic-cap',
            'state' => $status === S::Released ? 'done' : ($ready ? 'current' : 'upcoming'), 'date' => $request->released_at],
    ];

    $current = collect($milestones)->first(fn ($milestone) => $milestone['state'] === 'current')
        ?? ($status === S::Released ? end($milestones) : null);
@endphp

<div {{ $attributes->class('space-y-5') }}>
    <ol class="flex flex-col md:flex-row" data-step-bar>
        @foreach ($milestones as $index => ['title' => $title, 'task' => $task, 'icon' => $icon, 'state' => $state, 'date' => $date])
            @php $nextReached = ($milestones[$index + 1]['state'] ?? 'upcoming') !== 'upcoming'; @endphp
            <li @class(['relative flex gap-4 md:flex-1 md:flex-col md:items-center md:gap-3 md:text-center', 'pb-6 md:pb-0' => ! $loop->last])
                data-step-state="{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
                @unless ($loop->last)
                    {{-- The line to the next milestone: down on phones, across from md up. --}}
                    <span @class([
                        'absolute start-[calc(1.5rem-2px)] top-12 bottom-0 w-1',
                        'md:start-[calc(50%+1.5rem)] md:end-[calc(-50%+1.5rem)] md:top-[calc(1.5rem-2px)] md:bottom-auto md:h-1 md:w-auto',
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
                <div class="pt-1 md:px-1 md:pt-0">
                    <p @class(['text-sm font-medium', 'text-ink' => $state !== 'upcoming', 'text-zinc-500' => $state === 'upcoming'])>
                        {{ $title }}
                        @if ($task)
                            <span class="font-normal text-zinc-600">({{ strtolower($task) }})</span>
                        @endif
                        <span class="sr-only">({{ match ($state) { 'done' => __('done'), 'current' => __('current'), default => __('not yet') } }})</span>
                    </p>
                    @if ($state === 'done' && $date)
                        <p class="mt-0.5 text-xs text-zinc-600">
                            <time datetime="{{ $date->toIso8601String() }}">{{ $date->format('M j, Y') }}<span class="md:block"><span class="md:hidden"> · </span>{{ $date->format('g:i A') }}</span></time>
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
            <p class="font-semibold text-primary">{{ $current['title'] }}</p>
            <p class="text-zinc-700">{{ $current['text'] }}</p>
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
