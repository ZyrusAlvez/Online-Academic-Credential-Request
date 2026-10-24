@props(['steps'])

{{-- The offices a request passes through, with each one's task (names only, no staff). --}}
<ol {{ $attributes->class('space-y-3') }}>
    @foreach ($steps as $step)
        <li @class([
            'flex gap-3 rounded-lg p-3',
            'bg-primary-50 ring-1 ring-primary/30' => $step->status === \App\Enums\StepStatus::Current,
        ])>
            <span @class([
                'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                'bg-green-700 text-white' => $step->status === \App\Enums\StepStatus::Completed,
                'bg-primary text-white' => $step->status === \App\Enums\StepStatus::Current,
                'bg-zinc-200 text-zinc-700' => in_array($step->status, [\App\Enums\StepStatus::Waiting, \App\Enums\StepStatus::Bypassed], true),
            ]) aria-hidden="true">
                @if ($step->status === \App\Enums\StepStatus::Completed)
                    <flux:icon.check variant="micro" />
                @else
                    {{ $step->position }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium text-ink">{{ $step->department->name }}</p>
                    <flux:badge size="sm" :color="$step->status->color()">{{ $step->status->label() }}</flux:badge>
                </div>
                <p class="text-xs text-zinc-600">
                    {{ $step->task->label() }}
                    @if ($step->completed_at) · {{ $step->completed_at->format('M j, Y g:i A') }} @endif
                </p>
            </div>
        </li>
    @endforeach
</ol>
