@props(['visits', 'showActors' => false])

{{--
    The internal monitoring table of the client's workflow (VI-C, VI-D): every visit kept
    separately, with when the paper reached the office, when its task was done there and when it
    left (REQ-17-14).
--}}
@php
    $stamp = function (?\Illuminate\Support\Carbon $at, ?\App\Models\User $by) use ($showActors): string {
        if ($at === null) {
            return '—';
        }

        $who = $showActors && $by ? '<div class="text-xs text-zinc-500">'.e($by->name).'</div>' : '';

        return e($at->format('M j, g:i A')).$who;
    };
@endphp

<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Office') }}</flux:table.column>
        <flux:table.column>{{ __('Received') }}</flux:table.column>
        <flux:table.column class="max-sm:hidden">{{ __('Done') }}</flux:table.column>
        <flux:table.column class="max-sm:hidden">{{ __('Left') }}</flux:table.column>
        <flux:table.column class="max-md:hidden">{{ __('Time spent') }}</flux:table.column>
        <flux:table.column>{{ __('Status') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @foreach ($visits as $visit)
            <flux:table.row :key="$visit->id" data-visit="{{ $visit->id }}">
                <flux:table.cell class="whitespace-normal">
                    <div class="font-medium text-ink">{{ $visit->department->name }}</div>
                    <div class="text-xs text-zinc-500">
                        {{ $visit->purpose === \App\Enums\VisitPurpose::Route ? $visit->step->task->label() : $visit->purpose->label() }}
                        @if ($visit->visit_no > 1) · {{ __('visit :n', ['n' => $visit->visit_no]) }} @endif
                    </div>
                    <div class="text-xs text-zinc-500">{{ __('Forwarded :date', ['date' => $visit->forwarded_at->format('M j, g:i A')]) }}</div>
                    @if ($visit->return_reason)
                        <div class="mt-1 text-xs text-orange-800">{{ __('Returned to :office: :reason', ['office' => $visit->returnedTo?->name, 'reason' => $visit->return_reason]) }}</div>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="whitespace-normal text-sm" data-received>
                    @if ($visit->received_at)
                        {!! $stamp($visit->received_at, $visit->receiver) !!}
                    @else
                        <span class="text-zinc-500">{{ __('Not yet received') }}</span>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="max-sm:hidden whitespace-normal text-sm" data-done>{!! $stamp($visit->done_at, $visit->doer) !!}</flux:table.cell>
                <flux:table.cell class="max-sm:hidden whitespace-normal text-sm" data-left>{!! $stamp($visit->completed_at, $visit->completer) !!}</flux:table.cell>
                <flux:table.cell class="max-md:hidden tabular-nums">
                    @if ($visit->working_days !== null)
                        {{ $visit->working_days === 0 ? __('Same day') : trans_choice(':count working day|:count working days', $visit->working_days) }}
                    @elseif ($visit->received_at)
                        <span class="text-zinc-500">{{ __('In progress') }}</span>
                    @else
                        —
                    @endif
                </flux:table.cell>
                <flux:table.cell>
                    <flux:badge size="sm" :color="$visit->status->color()">{{ __($visit->status->label()) }}</flux:badge>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
