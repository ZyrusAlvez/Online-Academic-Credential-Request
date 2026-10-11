@props(['events', 'showActors' => false])

@php
    $icons = [
        'submitted' => 'paper-airplane', 'payment_submitted' => 'arrow-up-tray', 'payment_rejected' => 'x-circle',
        'payment_accepted' => 'banknotes', 'college_changed' => 'pencil-square', 'routed' => 'arrows-right-left',
        'step_signed' => 'check-circle', 'step_held' => 'pause-circle', 'step_resumed' => 'play-circle',
        'step_skipped' => 'forward', 'ready_for_release' => 'gift', 'released' => 'hand-raised', 'cancelled' => 'no-symbol',
    ];
@endphp

<ol {{ $attributes->class('relative space-y-6 border-s-2 border-secondary/50 ps-6') }}>
    @foreach ($events->sortByDesc('id') as $event)
        <li class="relative">
            <span @class([
                'absolute -start-[2.2rem] flex size-7 items-center justify-center rounded-full ring-4 ring-white',
                'bg-primary text-white' => $loop->first,
                'bg-primary-100 text-primary' => ! $loop->first,
            ]) aria-hidden="true">
                <flux:icon :name="$icons[$event->type->value] ?? 'information-circle'" variant="micro" />
            </span>
            <p @class(['font-medium', 'text-primary' => $loop->first, 'text-ink' => ! $loop->first])>{{ $event->summary() }}</p>
            <p class="text-xs text-zinc-600">
                <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y · g:i A') }}</time>
                @if ($showActors && $event->actor)
                    · {{ $event->actor->name }} ({{ $event->actor->role->label() }})
                @endif
            </p>
            @if ($event->remarks)
                <p class="mt-1 rounded-md bg-primary-50 px-3 py-2 text-sm text-zinc-800">{{ $event->remarks }}</p>
            @endif
        </li>
    @endforeach
</ol>
