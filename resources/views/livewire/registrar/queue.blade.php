@php
    use App\Enums\RegistrarQueue as Q;
    use App\Enums\RequestStatus as S;
    use App\Enums\VisitStatus;

    $outgoing = $queue === Q::Outgoing;
    $tracking = $queue->tracksOffices();
    // The cashier's Payment Validation renders this view too, without a selection.
    $pageIds ??= [];
    $selected ??= [];
    $allTicked = $pageIds !== [] && array_diff($pageIds, array_map('strval', $selected)) === [];
@endphp

<div class="space-y-6" data-queue="{{ $queue->value }}">
    {{-- The cashier's Payment Validation uses this list too, with its own description (REQ-25-04). --}}
    <x-page-header :title="__($queue->label())" :description="$description ?? __($queue->description())" />

    {{-- Tabs on the left, search on the right of the same line (wraps on small screens). --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        @if ($hasWaitingTab)
            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Show') }}" data-queue-tabs>
                <flux:button size="sm" wire:click="$set('waiting', false)" :variant="$showWaiting ? 'outline' : 'primary'" aria-pressed="{{ $showWaiting ? 'false' : 'true' }}">{{ __($queue->activeTabLabel()) }}</flux:button>
                <flux:button size="sm" wire:click="$set('waiting', true)" :variant="$showWaiting ? 'primary' : 'outline'" aria-pressed="{{ $showWaiting ? 'true' : 'false' }}">{{ __('Waiting for the requester (:count)', ['count' => $waitingCount]) }}</flux:button>
            </div>
        @endif

        <div @class([
            'grid w-full gap-3',
            'sm:ml-auto sm:w-72' => $hasWaitingTab,
            'md:grid-cols-2 xl:grid-cols-3' => ! $hasWaitingTab,
        ]) data-queue-search>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only :placeholder="__('Tracking no. or name')" clearable />
            @if ($tracking)
                <flux:select wire:model.live="office" :label="__('Currently at')" label:sr-only>
                    <flux:select.option value="">{{ __('Any office') }}</flux:select.option>
                    @foreach ($offices as $option)
                        <flux:select.option :value="$option->id">{{ __('At :office', ['office' => $option->name]) }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        @if ($outgoing)
            {{-- Each goes to its own next office (REQ-32-03). --}}
            <flux:button variant="primary" icon="paper-airplane" wire:click="forwardSelected" :disabled="$selected === []" data-forward-selected
                data-confirm-title="{{ __('Forward documents') }}" data-confirm-label="{{ __('Forward') }}" :wire:confirm="trans_choice('Forward :count document to its next office?|Forward :count documents to their next offices?', count($selected))">
                {{ $selected === [] ? __('Forward') : __('Forward (:count)', ['count' => count($selected)]) }}
            </flux:button>
        @endif
    </div>

    <flux:table :paginate="$requests">
        <flux:table.columns>
            @if ($outgoing)
                <flux:table.column class="w-8">
                    <input type="checkbox" class="size-4 cursor-pointer accent-primary" aria-label="{{ __('Select every document on this page') }}"
                        wire:click="togglePage(@js($pageIds))" @checked($allTicked) @disabled($pageIds === []) data-select-page>
                </flux:table.column>
            @endif
            <flux:table.column>{{ __('Request') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Requester') }}</flux:table.column>
            @if ($tracking)
                <flux:table.column>{{ __('Currently at') }}</flux:table.column>
                <flux:table.column>{{ __('Next') }}</flux:table.column>
                <flux:table.column class="max-sm:hidden">{{ __('Due') }}</flux:table.column>
            @elseif ($queue === Q::FinalCheck)
                <flux:table.column>{{ __('Document') }}</flux:table.column>
                <flux:table.column class="max-sm:hidden">{{ __('Due') }}</flux:table.column>
            @elseif ($queue === Q::Release)
                <flux:table.column>{{ __('Ready since') }}</flux:table.column>
            @else
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="max-sm:hidden">{{ $queue === Q::Payment ? __('Amount due') : __('Submitted') }}</flux:table.column>
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($requests as $request)
                @php
                    $visit = $request->openVisit;
                    // Outgoing has no working page: its rows open the record (REQ-32-02).
                    $rowRoute = $queue->hasWorkingPage() ? $linkRoute : 'records.show';
                @endphp
                <flux:table.row :key="$request->id">
                    @if ($outgoing)
                        <flux:table.cell>
                            <input type="checkbox" class="size-4 cursor-pointer accent-primary" value="{{ $request->id }}" wire:model.live="selected"
                                aria-label="{{ __('Select :ref', ['ref' => $request->reference_no]) }}" data-select-row>
                        </flux:table.cell>
                    @endif
                    <flux:table.cell class="whitespace-normal">
                        <a href="{{ route($rowRoute, $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                        <x-walk-in-badge :request="$request" class="ms-1" />
                        <div class="text-xs text-zinc-600">{{ $request->credentialType->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal">
                        <div class="text-ink">{{ $request->requester_name }}</div>
                        <div class="text-xs text-zinc-500">{{ $request->student_number }}</div>
                    </flux:table.cell>

                    @if ($tracking || $queue === Q::FinalCheck)
                        <flux:table.cell class="whitespace-normal" data-current-office>
                            {{-- A forwarded paper stays with its sender until it is received (REQ-17-18). --}}
                            @if ($tracking)
                                <div class="font-medium text-ink">{{ $visit?->holder()->name ?? '—' }}</div>
                            @endif
                            @if ($visit)
                                <div class="text-xs text-zinc-600">
                                    {{ match (true) {
                                        $visit->status === VisitStatus::InTransit => __('Collected :time for :office, not yet received', ['time' => $visit->forwarded_at->diffForHumans(), 'office' => $visit->department->name]),
                                        $visit->isPendingReturn() => __('Returned :time', ['time' => $visit->done_at->diffForHumans()]),
                                        $visit->status === VisitStatus::Done => __('Done :time, not yet forwarded', ['time' => $visit->done_at->diffForHumans()]),
                                        default => __('Received :time', ['time' => $visit->received_at->diffForHumans()]),
                                    } }}
                                </div>
                            @endif
                            @if ($request->status === S::ReturnedForCorrection)
                                <flux:badge size="sm" color="orange" class="mt-1">{{ __('Returned for correction') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        @if ($tracking)
                            @php($next = $request->nextOffice())
                            <flux:table.cell class="whitespace-normal" data-next-office>
                                {{-- Outgoing forwards every office's finished paper (REQ-32-03). The button names the next office. --}}
                                @if ($next)
                                    @php($label = $visit->isPendingReturn() ? __('Return to :office', ['office' => $next->name]) : __('Forward to :office', ['office' => $next->name]))
                                    <flux:button size="sm" variant="primary" :icon="$visit->isPendingReturn() ? 'arrow-uturn-left' : 'paper-airplane'"
                                        wire:click="forward({{ $request->id }})" data-forward
                                        data-confirm-title="{{ __('Forward document') }}" data-confirm-label="{{ __('Forward') }}" :wire:confirm="__('Bring :ref to :office now?', ['ref' => $request->reference_no, 'office' => $next->name])">
                                        {{ $label }}
                                    </flux:button>
                                @else
                                    <div class="text-ink" data-next-name>{{ $next?->name ?? '—' }}</div>
                                @endif
                            </flux:table.cell>
                        @endif
                        <flux:table.cell class="max-sm:hidden">
                            <div class="flex flex-wrap items-center gap-1 text-zinc-700">
                                {{ $request->effective_due_at?->format('M j, Y') ?? '—' }}
                                @if ($request->isOverdue())
                                    <flux:badge size="sm" color="red" icon="clock">{{ __('Overdue') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                    @elseif ($queue === Q::Release)
                        <flux:table.cell class="text-zinc-700">{{ $request->ready_at?->format('M j, Y') }}</flux:table.cell>
                    @else
                        <flux:table.cell class="whitespace-normal">
                            <div class="flex flex-wrap gap-1">
                                <x-request-status :status="$request->status" />
                                @if ($request->status === S::UnderEvaluation && $request->evaluator)
                                    {{-- The evaluator owns it until the decision (REQ-16-09). --}}
                                    <flux:badge size="sm" color="zinc" icon="lock-closed" data-evaluator>{{ __('Evaluating: :name', ['name' => $request->evaluator->name]) }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="max-sm:hidden text-zinc-600">
                            {{ $queue === Q::Payment ? '₱'.number_format((float) $request->amount_due, 2) : $request->submitted_at?->format('M j, Y') }}
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">{{ trim($search) !== '' ?__('No requests match your search.') : __('Nothing here right now.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
