@props(['visits', 'showActors' => false])

{{-- The internal monitoring table of the client's workflow (VI-D): every visit kept separately. --}}
<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Office') }}</flux:table.column>
        <flux:table.column>{{ __('Received') }}</flux:table.column>
        <flux:table.column>{{ __('Released') }}</flux:table.column>
        <flux:table.column>{{ __('Time spent') }}</flux:table.column>
        <flux:table.column>{{ __('Status') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @foreach ($visits as $visit)
            <flux:table.row :key="$visit->id">
                <flux:table.cell class="whitespace-normal">
                    <div class="font-medium text-ink">{{ $visit->department->name }}</div>
                    <div class="text-xs text-zinc-500">
                        {{ $visit->purpose === \App\Enums\VisitPurpose::Route ? $visit->step->task->label() : $visit->purpose->label() }}
                        @if ($visit->visit_no > 1) · {{ __('visit :n', ['n' => $visit->visit_no]) }} @endif
                    </div>
                    @if ($visit->return_reason)
                        <div class="mt-1 text-xs text-orange-800">{{ __('Returned to :office: :reason', ['office' => $visit->returnedTo?->name, 'reason' => $visit->return_reason]) }}</div>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="whitespace-normal text-sm">
                    @if ($visit->received_at)
                        {{ $visit->received_at->format('M j, g:i A') }}
                        @if ($showActors && $visit->receiver)<div class="text-xs text-zinc-500">{{ $visit->receiver->name }}</div>@endif
                    @else
                        <span class="text-zinc-500">{{ __('Forwarded :date', ['date' => $visit->forwarded_at->format('M j, g:i A')]) }}</span>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="whitespace-normal text-sm">
                    @if ($visit->completed_at)
                        {{ $visit->completed_at->format('M j, g:i A') }}
                        @if ($showActors && $visit->completer)<div class="text-xs text-zinc-500">{{ $visit->completer->name }}</div>@endif
                    @else
                        —
                    @endif
                </flux:table.cell>
                <flux:table.cell class="tabular-nums">
                    @if ($visit->working_days !== null)
                        {{ $visit->working_days === 0 ? __('Same day') : trans_choice(':count working day|:count working days', $visit->working_days) }}
                    @elseif ($visit->received_at)
                        <span class="text-zinc-500">{{ __('In progress') }}</span>
                    @else
                        —
                    @endif
                </flux:table.cell>
                <flux:table.cell>
                    <flux:badge size="sm" :color="$visit->status->color()">{{ $visit->status->label() }}</flux:badge>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
