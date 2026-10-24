@props(['visits', 'linkRoute', 'empty' => 'Nothing waiting.', 'history' => false])

<flux:table :paginate="$visits instanceof \Illuminate\Contracts\Pagination\Paginator ? $visits : null">
    <flux:table.columns>
        <flux:table.column>{{ __('Request') }}</flux:table.column>
        <flux:table.column class="max-md:hidden">{{ __('Requester') }}</flux:table.column>
        <flux:table.column class="max-lg:hidden">{{ __('Task') }}</flux:table.column>
        <flux:table.column>{{ $history ? __('Closed') : __('Since') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @forelse ($visits as $visit)
            @php($request = $visit->credentialRequest)
            <flux:table.row :key="$visit->id">
                <flux:table.cell class="whitespace-normal">
                    <a href="{{ route($linkRoute, $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                    <div class="text-xs text-zinc-600">{{ $request->credentialType->name }}</div>
                    @if ($visit->purpose !== \App\Enums\VisitPurpose::Route)
                        <flux:badge size="sm" color="orange" class="mt-1">{{ $visit->purpose === \App\Enums\VisitPurpose::Correction ? __('For correction') : __('Corrected, sent back') }}</flux:badge>
                    @endif
                    @if (! $history && $request->isOverdue())
                        <flux:badge size="sm" color="red" class="mt-1">{{ __('Overdue') }}</flux:badge>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="max-md:hidden whitespace-normal">{{ $request->student->name }}</flux:table.cell>
                <flux:table.cell class="max-lg:hidden">
                    {{ $visit->step->task->label() }}
                    @if ($visit->fromDepartment)
                        <div class="text-xs text-zinc-500">{{ __('from :office', ['office' => $visit->fromDepartment->code]) }}</div>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="text-zinc-700">
                    @if ($history)
                        <flux:badge size="sm" :color="$visit->status->color()">{{ $visit->status->label() }}</flux:badge>
                        <div class="text-xs text-zinc-600">{{ $visit->completed_at?->format('M j, Y g:i A') }}@if ($visit->completer) · {{ $visit->completer->name }}@endif</div>
                    @else
                        @php($since = $visit->received_at ?? $visit->forwarded_at)
                        <time datetime="{{ $since->toIso8601String() }}" title="{{ $since->format('M j, Y g:i A') }}">{{ $since->diffForHumans(short: true) }}</time>
                    @endif
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">{{ $empty }}</flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>
