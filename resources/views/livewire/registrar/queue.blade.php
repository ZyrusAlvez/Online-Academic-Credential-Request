@php
    use App\Enums\RegistrarQueue as Q;
    use App\Enums\RequestStatus as S;
    use App\Enums\VisitStatus;
@endphp

<div class="space-y-6" data-queue="{{ $queue->value }}">
    <x-page-header :title="__($queue->label())" :description="__($queue->description())" />

    @if ($queue === Q::Evaluation)
        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Show') }}">
            <flux:button size="sm" wire:click="$set('waiting', false)" :variant="$showWaiting ? 'outline' : 'primary'" aria-pressed="{{ $showWaiting ? 'false' : 'true' }}">{{ __('To evaluate') }}</flux:button>
            <flux:button size="sm" wire:click="$set('waiting', true)" :variant="$showWaiting ? 'primary' : 'outline'" aria-pressed="{{ $showWaiting ? 'true' : 'false' }}">{{ __('Waiting for the requester (:count)', ['count' => $waitingCount]) }}</flux:button>
        </div>
    @endif

    @if ($queue === Q::InProgress)
        <div class="max-w-sm">
            <flux:select wire:model.live="office" :label="__('Currently at')" label:sr-only>
                <flux:select.option value="">{{ __('Any office') }}</flux:select.option>
                @foreach ($offices as $option)
                    <flux:select.option :value="$option->id">{{ __('At :office', ['office' => $option->name]) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    @endif

    <flux:table :paginate="$requests">
        <flux:table.columns>
            <flux:table.column>{{ __('Request') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Requester') }}</flux:table.column>
            @if ($queue === Q::InProgress)
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
                    // In Progress: where the Registrar acts next (accepts receipt or forwards), the working
                    // page; the rest, the record (REQ-17-12).
                    $registrarActs = $visit?->department->code === \App\Models\Department::REGISTRAR_CODE;
                    $rowRoute = $queue === Q::InProgress && ! $registrarActs ? 'registrar.records.show' : $linkRoute;
                @endphp
                <flux:table.row :key="$request->id">
                    <flux:table.cell class="whitespace-normal">
                        <a href="{{ route($rowRoute, $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                        <div class="text-xs text-zinc-600">{{ $request->credentialType->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal">
                        <div class="text-ink">{{ $request->requester_name }}</div>
                        <div class="text-xs text-zinc-500">{{ $request->student_number }}</div>
                    </flux:table.cell>

                    @if ($queue === Q::InProgress || $queue === Q::FinalCheck)
                        <flux:table.cell class="whitespace-normal" data-current-office>
                            {{-- A forwarded paper stays with its sender until it is received (REQ-17-18). --}}
                            @if ($queue === Q::InProgress)
                                <div class="font-medium text-ink">{{ $visit?->holder()->name ?? '—' }}</div>
                            @endif
                            @if ($visit)
                                <div class="text-xs text-zinc-600">
                                    {{ match ($visit->status) {
                                        VisitStatus::InTransit => __('Forwarded :time to :office, not yet received', ['time' => $visit->forwarded_at->diffForHumans(), 'office' => $visit->department->name]),
                                        VisitStatus::Done => __('Done :time, not yet forwarded', ['time' => $visit->done_at->diffForHumans()]),
                                        default => __('Received :time', ['time' => $visit->received_at->diffForHumans()]),
                                    } }}
                                </div>
                            @endif
                            @if ($request->status === S::ReturnedForCorrection)
                                <flux:badge size="sm" color="orange" class="mt-1">{{ __('Returned for correction') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        @if ($queue === Q::InProgress)
                            @php($next = $request->nextOffice())
                            <flux:table.cell class="whitespace-normal" data-next-office>
                                {{-- The Registrar's own papers are forwarded here only (REQ-17-12); the button names the next office. --}}
                                @if ($next && $registrarActs && $visit->isForwardable())
                                    <flux:button size="sm" variant="primary" icon="paper-airplane"
                                        wire:click="forward({{ $request->id }})" data-forward
                                        data-confirm-title="{{ __('Forward document') }}" data-confirm-label="{{ __('Forward') }}" :wire:confirm="__('Is the paper of :ref leaving :from for :office now? They confirm when it arrives.', ['ref' => $request->reference_no, 'from' => $visit->department->name, 'office' => $next->name])">
                                        {{ __('Forward to :office', ['office' => $next->name]) }}
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
                    <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">{{ __('Nothing here right now.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
