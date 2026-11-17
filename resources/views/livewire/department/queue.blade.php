@php
    use App\Enums\DepartmentQueue as Q;
    use App\Enums\VisitPurpose;
    use App\Enums\VisitStatus;

    $bulk = $queue->hasCheckboxes();
    $outgoing = $queue === Q::Outgoing;
    $history = $queue === Q::History;
    $allTicked = $pageIds !== [] && array_diff($pageIds, array_map('strval', $selected)) === [];
@endphp

<div class="space-y-6" data-department-queue="{{ $queue->value }}">
    <x-page-header :title="__($queue->label())" :description="__($queue->description())" />

    @unless ($department)
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.text>{{ __('Your account is not assigned to an office yet. Please contact the Office of the Registrar.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    {{-- Search and filter on the left, the bulk action on the right (wraps on small screens). --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-2" data-queue-search>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only :placeholder="__('Tracking no. or name')" clearable class="sm:w-64" />
            <flux:select wire:model.live="type" :label="__('Credential type')" label:sr-only class="sm:w-64" data-type-filter>
                <flux:select.option value="">{{ __('Any credential') }}</flux:select.option>
                @foreach ($types as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($queue === Q::Incoming)
            <flux:button variant="primary" icon="inbox-arrow-down" wire:click="acknowledge" :disabled="$selected === []" data-acknowledge
                data-confirm-title="{{ __('Acknowledge receipt') }}" data-confirm-label="{{ __('Acknowledge') }}" :wire:confirm="trans_choice('Acknowledge receipt of :count document?|Acknowledge receipt of :count documents?', count($selected))">
                {{ $selected === [] ? __('Acknowledge receipt') : __('Acknowledge receipt (:count)', ['count' => count($selected)]) }}
            </flux:button>
        @endif
    </div>

    <flux:table :paginate="$visits">
        <flux:table.columns>
            @if ($bulk)
                <flux:table.column class="w-8">
                    <input type="checkbox" class="size-4 cursor-pointer accent-primary" aria-label="{{ __('Select every document on this page') }}"
                        wire:click="togglePage(@js($pageIds))" @checked($allTicked) @disabled($pageIds === []) data-select-page>
                </flux:table.column>
            @endif
            <flux:table.column sortable :sorted="$sortColumn === 'request'" :direction="$sortDirection" wire:click="sortBy('request')" class="cursor-pointer">{{ __('Request') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortColumn === 'requester'" :direction="$sortDirection" wire:click="sortBy('requester')" class="cursor-pointer max-md:hidden">{{ __('Requester') }}</flux:table.column>
            @if ($history)
                <flux:table.column>{{ __('Action') }}</flux:table.column>
            @elseif ($outgoing)
                <flux:table.column>{{ __('Next') }}</flux:table.column>
            @endif
            <flux:table.column sortable :sorted="$sortColumn === 'since'" :direction="$sortDirection" wire:click="sortBy('since')" class="cursor-pointer">{{ $history ? __('Date') : __('Since') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($visits as $visit)
                @php($request = $visit->credentialRequest)
                <flux:table.row :key="$visit->id">
                    @if ($bulk)
                        <flux:table.cell>
                            <input type="checkbox" class="size-4 cursor-pointer accent-primary" value="{{ $visit->id }}" wire:model.live="selected"
                                aria-label="{{ __('Select :ref', ['ref' => $request->reference_no]) }}" data-select-row>
                        </flux:table.cell>
                    @endif
                    <flux:table.cell class="whitespace-normal">
                        <a href="{{ route($queue->showRouteName(), $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                        <div class="text-xs text-zinc-600">{{ $request->credentialType->name }}</div>
                        @if ($visit->purpose !== VisitPurpose::Route)
                            <flux:badge size="sm" color="orange" class="mt-1">{{ $visit->purpose === VisitPurpose::Correction ? __('For correction') : __('Corrected, sent back') }}</flux:badge>
                        @endif
                        @if (! $history && $visit->status === VisitStatus::Received && $visit->due_at)
                            <div class="text-xs text-zinc-600" data-office-deadline>{{ __('Due :date', ['date' => $visit->due_at->format('D, M j')]) }}</div>
                        @endif
                        @if (! $history && ($visit->isOverdue() || $request->isOverdue()))
                            <flux:badge size="sm" color="red" class="mt-1">{{ __('Overdue') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal">{{ $request->requester_name }}</flux:table.cell>

                    @if ($history)
                        <flux:table.cell class="whitespace-normal" data-action>
                            {{ match (true) {
                                $visit->status === VisitStatus::Returned => __('Returned to :office', ['office' => $visit->returnedTo?->name]),
                                $visit->status === VisitStatus::Bypassed => __($visit->status->label()),
                                $visit->purpose === VisitPurpose::Correction => __('Corrected, sent back'),
                                default => __($visit->step->task->doneLabel()),
                            } }}
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-normal text-zinc-700">
                            {{ $visit->completed_at?->format('M j, Y g:i A') }}
                            @if ($visit->completer)
                                <div class="text-xs text-zinc-600">{{ $visit->completer->name }}</div>
                            @endif
                        </flux:table.cell>
                    @else
                        @if ($outgoing)
                            {{-- The Registrar collects it and brings it there (REQ-32-05). --}}
                            @php($next = $visit->nextStep()?->department)
                            <flux:table.cell class="whitespace-normal text-ink" data-next-office>
                                {{ match (true) {
                                    $next === null => '—',
                                    $visit->isPendingReturn() => __('Return to :office', ['office' => $next->name]),
                                    default => $next->name,
                                } }}
                            </flux:table.cell>
                        @endif
                        <flux:table.cell class="text-zinc-700">
                            @php($since = match ($queue) { Q::Incoming => $visit->forwarded_at, Q::Outgoing => $visit->done_at, default => $visit->received_at })
                            <time datetime="{{ $since->toIso8601String() }}" title="{{ $since->format('M j, Y g:i A') }}">{{ $since->diffForHumans(short: true) }}</time>
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">{{ trim($search) !== '' || $type !== '' ? __('No documents match.') : __($queue->emptyText()) }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
