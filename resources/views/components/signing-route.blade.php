@props(['steps', 'showActors' => false])

<ol {{ $attributes->class('space-y-3') }}>
    @foreach ($steps as $step)
        <li @class([
            'flex gap-3 rounded-lg p-3',
            'bg-primary-50 ring-1 ring-primary/30' => $step->status->isCurrent(),
        ])>
            <span @class([
                'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                'bg-green-700 text-white' => $step->status === \App\Enums\StepStatus::Signed,
                'bg-primary text-white' => $step->status === \App\Enums\StepStatus::Pending,
                'bg-amber-500 text-white' => $step->status === \App\Enums\StepStatus::OnHold,
                'bg-zinc-200 text-zinc-700' => in_array($step->status, [\App\Enums\StepStatus::Waiting, \App\Enums\StepStatus::Skipped], true),
            ]) aria-hidden="true">
                @if ($step->status === \App\Enums\StepStatus::Signed)
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
                @if ($step->acted_at)
                    <p class="text-xs text-zinc-600">
                        {{ $step->acted_at->format('M j, Y g:i A') }}
                        @if ($showActors && $step->actor) · {{ $step->actor->name }} @endif
                    </p>
                @endif
                @if ($step->remarks)
                    <p class="mt-1 text-sm text-zinc-700">{{ $step->remarks }}</p>
                @endif
                @if ($showActors && $step->instructions && ! $step->status->isDone())
                    <p class="mt-1 text-xs text-zinc-500">{{ __('Instructions: :text', ['text' => $step->instructions]) }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
