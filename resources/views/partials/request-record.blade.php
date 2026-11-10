{{--
    The read-only record of a request for the registrar: details, dates, payments, representatives,
    route, office visits, delays, release and the ledger (REQ-16-07). Requires $request with its
    relations loaded. Sections already shown by the current stage's checklist are skipped:
    $hide is a list of 'details', 'deficiencies', 'proofs', 'representatives', 'route', 'ledger'.
--}}
@php($hide ??= [])

@unless (in_array('details', $hide, true))
    <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request Details') }}</h2>
        <x-request-details :request="$request" />
        <dl class="mt-4 grid gap-4 border-t border-secondary/50 pt-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-zinc-500">{{ __('Email and contact number') }}</dt>
                <dd class="font-medium break-all text-ink">{{ $request->student->email }}@if ($request->contact_number) · {{ $request->contact_number }}@endif</dd>
            </div>
            <div>
                <dt class="text-zinc-500">{{ __('Valid ID') }}</dt>
                <dd class="font-medium text-ink">
                    @if ($request->identityDocument)
                        <x-file-link :href="route('identity-documents.show', $request->identityDocument)" :name="$request->identityDocument->id_type.' – '.$request->requester_name" :type="$request->identityDocument->mime_type">
                            {{ $request->identityDocument->id_type }}
                        </x-file-link>
                        <flux:badge size="sm" :color="$request->identity_status->color()" class="ms-1">{{ $request->identity_status->label() }}</flux:badge>
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>
        @if ($request->requirementFiles() !== [])
            <div class="mt-4 border-t border-secondary/50 pt-4">
                <p class="mb-2 text-sm text-zinc-500">{{ __('Documentary requirements') }}</p>
                <x-requirement-document-list :request="$request" />
            </div>
        @endif
        @if ($request->accepted_at)
            <dl class="mt-4 grid gap-4 border-t border-secondary/50 pt-4 text-sm sm:grid-cols-3">
                <div><dt class="text-zinc-500">{{ __('Due date') }}</dt><dd class="font-medium text-ink">{{ $request->due_at->format('M j, Y') }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Adjusted due date') }}</dt><dd class="font-medium text-ink">{{ $request->adjusted_due_at?->format('M j, Y') ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Processing time') }}</dt><dd class="font-medium text-ink">{{ $request->processing_working_days !== null ? trans_choice(':count working day|:count working days', $request->processing_working_days) : '—' }}</dd></div>
            </dl>
        @endif
    </section>
@endunless

@if (! in_array('deficiencies', $hide, true) && $request->deficiencies->isNotEmpty())
    {{-- What an office visit required, and when each was received (REQ-20-04, 09). --}}
    <section aria-labelledby="deficiencies-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="deficiencies-heading" class="text-lg font-semibold text-primary">{{ __('Missing Requirements') }}</h2>
        <ul class="space-y-1 text-sm">
            @foreach ($request->deficiencies as $deficiency)
                <li wire:key="deficiency-{{ $deficiency->id }}">
                    <span class="font-medium text-ink">{{ $deficiency->description }}</span>
                    @if ($deficiency->resolved_at)
                        <span class="text-xs text-green-800">· {{ __('Received :date', ['date' => $deficiency->resolved_at->format('M j, Y')]) }}</span>
                    @else
                        <span class="text-xs text-amber-800">· {{ __('Not yet received') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if (! in_array('proofs', $hide, true) && $request->paymentProofs->isNotEmpty())
    <section aria-labelledby="proofs-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="proofs-heading" class="text-lg font-semibold text-primary">{{ __('Proofs of Payment') }}</h2>
        <x-payment-proof-list :proofs="$request->paymentProofs" />
    </section>
@endif

@if (! in_array('representatives', $hide, true) && $request->representatives->isNotEmpty())
    <section aria-labelledby="representatives-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="representatives-heading" class="text-lg font-semibold text-primary">{{ __('Authorized Representatives') }}</h2>
        <x-representative-list :representatives="$request->representatives" />
    </section>
@endif

@if ($request->release)
    <section aria-labelledby="released-heading" class="space-y-1 rounded-xl border border-secondary/50 p-5 text-sm sm:p-6">
        <h2 id="released-heading" class="mb-2 text-lg font-semibold text-primary">{{ __('Release Record') }}</h2>
        <p class="text-ink">{{ __(':name (:type)', ['name' => $request->release->recipient_name, 'type' => $request->release->recipient_type->label()]) }}</p>
        @if ($request->release->id_presented)
            <p class="text-zinc-600">{{ __('ID presented: :id', ['id' => $request->release->id_presented]) }}</p>
        @endif
        <p class="text-zinc-600">{{ __('Released :date by :name', ['date' => $request->release->released_at->format('M j, Y g:i A'), 'name' => $request->release->releaser?->name]) }}</p>
    </section>
@endif

@if (! in_array('route', $hide, true) && $request->steps->isNotEmpty())
    <section aria-labelledby="route-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="route-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Route') }}</h2>
        <x-request-route :steps="$request->steps" />
    </section>
@endif

@if ($request->visits->isNotEmpty())
    <section aria-labelledby="visits-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="visits-heading" class="text-lg font-semibold text-primary">{{ __('Office Visits') }}</h2>
        <x-office-visits :visits="$request->visits" show-actors />
    </section>
@endif

@if ($request->delayNotes->isNotEmpty() || $request->pauses->isNotEmpty())
    <section aria-labelledby="delays-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="delays-heading" class="text-lg font-semibold text-primary">{{ __('Delays and Pauses') }}</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($request->delayNotes as $note)
                <li>
                    <flux:badge size="sm" color="amber">{{ $note->category->label() }}</flux:badge>
                    <span class="text-ink">{{ $note->reason }}</span>
                    <span class="text-xs text-zinc-500">· {{ $note->department?->code ?? __('Registrar') }} · {{ $note->recorder?->name }} · {{ $note->created_at->format('M j, g:i A') }}</span>
                    @if ($note->corrective_action)<div class="text-xs text-zinc-700">{{ __('Corrective action: :action', ['action' => $note->corrective_action]) }}</div>@endif
                </li>
            @endforeach
            @foreach ($request->pauses as $pause)
                <li>
                    <flux:badge size="sm" color="sky">{{ __('Paused for customer action') }}</flux:badge>
                    <span class="text-ink">{{ $pause->reason }}</span>
                    <span class="text-xs text-zinc-500">· {{ $pause->started_at->format('M j') }} – {{ $pause->ended_at?->format('M j') ?? __('ongoing') }}@if ($pause->working_days !== null) · {{ trans_choice(':count working day|:count working days', $pause->working_days) }}@endif</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@unless (in_array('ledger', $hide, true))
    <section aria-labelledby="history-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6" data-ledger>
        <h2 id="history-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('Tracking History') }}</h2>
        <x-request-timeline :events="$request->events" show-actors />
    </section>
@endunless
