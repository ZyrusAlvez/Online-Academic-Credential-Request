@php
    use App\Enums\RequestStatus as S;

    $status = $request->status;
    $owner = $request->student;
    $idDocument = $owner->latestIdentityDocument;
    $visit = $request->openVisit;
    $processing = in_array($status, S::processing(), true);
@endphp

<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('registrar.requests')" wire:navigate>{{ __('Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$owner->name">
        <x-slot:afterTitle>
            <x-copy-button :value="$request->reference_no" :label="__('Copy tracking number')" :copied="__('Tracking number copied.')" />
        </x-slot:afterTitle>
        <x-slot:actions>
            <x-request-status :status="$status" class="self-center" />
            @if ($request->isOverdue())
                <flux:badge color="red" icon="clock" class="self-center">{{ __('Overdue since :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
            @endif
            @can('cancel', $request)
                <flux:modal.trigger name="cancel-request">
                    <flux:button variant="ghost" icon="x-circle">{{ __('Cancel request') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request details') }}</h2>
                <x-request-details :request="$request" />
                @if ($request->accepted_at)
                    <dl class="mt-4 grid gap-4 border-t border-secondary/50 pt-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-zinc-500">{{ __('Due date') }}</dt><dd class="font-medium text-ink">{{ $request->due_at->format('M j, Y') }}</dd></div>
                        <div><dt class="text-zinc-500">{{ __('Adjusted due date') }}</dt><dd class="font-medium text-ink">{{ $request->adjusted_due_at?->format('M j, Y') ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">{{ __('Processing time') }}</dt><dd class="font-medium text-ink">{{ $request->processing_working_days !== null ? trans_choice(':count working day|:count working days', $request->processing_working_days) : '—' }}</dd></div>
                    </dl>
                @endif
            </section>

            <section aria-labelledby="requester-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="requester-heading" class="text-lg font-semibold text-primary">{{ __('Requester and identity') }}</h2>
                    <flux:badge :color="$owner->identity_status->color()">{{ $owner->identity_status->label() }}</flux:badge>
                </div>
                <p class="text-sm text-zinc-700">
                    <span class="font-medium text-ink">{{ $owner->name }}</span>
                    · <a href="mailto:{{ $owner->email }}" class="text-highlight underline underline-offset-2">{{ $owner->email }}</a>
                    @if ($owner->contact_number) · {{ $owner->contact_number }} @endif
                </p>
                @if ($idDocument)
                    <p class="text-sm">
                        <a href="{{ route('identity-documents.show', $idDocument) }}" target="_blank" rel="noopener" class="text-highlight underline underline-offset-2 hover:no-underline">
                            {{ __('Open :type', ['type' => $idDocument->id_type]) }}<span class="sr-only"> ({{ __('opens in a new tab') }})</span>
                        </a>
                        <span class="text-zinc-500">· {{ __('uploaded :date', ['date' => $idDocument->created_at->format('M j, Y')]) }}</span>
                    </p>
                    @if ($idDocument->status === \App\Enums\ReviewStatus::Pending)
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" variant="primary" icon="check" wire:click="verifyIdentity({{ $idDocument->id }})" :wire:confirm="__('Verify the identity of :name?', ['name' => $owner->name])">{{ __('Verify identity') }}</flux:button>
                            <flux:modal.trigger name="reject-identity">
                                <flux:button size="sm" variant="ghost" icon="x-mark">{{ __('Reject ID') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    @endif
                @else
                    <p class="text-sm text-zinc-600">{{ __('No valid ID uploaded yet.') }}</p>
                @endif
            </section>

            @if ($request->deficiencies->isNotEmpty())
                <section aria-labelledby="deficiencies-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="deficiencies-heading" class="text-lg font-semibold text-primary">{{ __('Missing requirements') }}</h2>
                    <ul class="divide-y divide-secondary/40 rounded-lg border border-secondary/50">
                        @foreach ($request->deficiencies as $deficiency)
                            <li class="flex flex-wrap items-start justify-between gap-3 p-3 text-sm" wire:key="deficiency-{{ $deficiency->id }}">
                                <div class="min-w-0">
                                    <p class="font-medium text-ink">{{ $deficiency->description }}</p>
                                    @if ($deficiency->resolved_at)
                                        <p class="text-xs text-green-800">{{ __('Complied :date · :by: :what', ['date' => $deficiency->resolved_at->format('M j, Y g:i A'), 'by' => $deficiency->resolver?->name, 'what' => $deficiency->resolution]) }}</p>
                                    @endif
                                </div>
                                @if (! $deficiency->resolved_at && $status === S::PendingCompliance)
                                    <flux:button size="sm" icon="check" wire:click="confirmResolve({{ $deficiency->id }})">{{ __('Record compliance') }}</flux:button>
                                @elseif ($deficiency->resolved_at)
                                    <flux:badge size="sm" color="green">{{ __('Complied') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="amber">{{ __('Missing') }}</flux:badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($request->requires_payment)
                <section aria-labelledby="proofs-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="proofs-heading" class="text-lg font-semibold text-primary">{{ __('Proof of payment') }}</h2>

                    @if ($pendingProof && $pendingProof->isImage())
                        <a href="{{ route('payment-proofs.show', $pendingProof) }}" target="_blank" rel="noopener" class="block">
                            <img src="{{ route('payment-proofs.show', $pendingProof) }}" alt="{{ __('Proof of payment uploaded by the requester') }}" class="max-h-96 w-auto rounded-lg border border-secondary/50" loading="lazy">
                        </a>
                    @endif

                    @if ($request->paymentProofs->isEmpty())
                        <p class="text-sm text-zinc-600">{{ __('No proof of payment uploaded yet.') }}</p>
                    @else
                        <x-payment-proof-list :proofs="$request->paymentProofs" />
                    @endif
                </section>
            @endif

            @if ($request->representatives->isNotEmpty())
                <section aria-labelledby="representatives-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="representatives-heading" class="text-lg font-semibold text-primary">{{ __('Authorized representatives') }}</h2>
                    <x-representative-list :representatives="$request->representatives" :reviewable="! $status->isFinal()" />
                </section>
            @endif

            @if ($request->visits->isNotEmpty())
                <section aria-labelledby="visits-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="visits-heading" class="text-lg font-semibold text-primary">{{ __('Office visits') }}</h2>
                    <x-office-visits :visits="$request->visits" show-actors />
                </section>
            @endif

            @if ($request->delayNotes->isNotEmpty() || $request->pauses->isNotEmpty())
                <section aria-labelledby="delays-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="delays-heading" class="text-lg font-semibold text-primary">{{ __('Delays and pauses') }}</h2>
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

            <section aria-labelledby="history-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="history-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('Ledger') }}</h2>
                <x-request-timeline :events="$request->events" show-actors />
            </section>
        </div>

        <aside class="space-y-6">
            @if ($status === S::Submitted)
                <section aria-labelledby="evaluate-heading" class="space-y-3 rounded-xl border border-primary bg-primary-50 p-5">
                    <h2 id="evaluate-heading" class="text-lg font-semibold text-primary">{{ __('Evaluation') }}</h2>
                    <p class="text-sm text-zinc-700">{{ __('Received :time. Take it up to check the identity, academic records and requirements.', ['time' => $request->submitted_at->diffForHumans()]) }}</p>
                    <flux:button wire:click="startEvaluation" variant="primary" icon="magnifying-glass" class="w-full">{{ __('Start evaluation') }}</flux:button>
                </section>
            @endif

            @if (in_array($status, [S::UnderEvaluation, S::PendingCompliance], true))
                <section aria-labelledby="evaluate-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5">
                    <div>
                        <h2 id="evaluate-heading" class="text-lg font-semibold text-primary">{{ __('Evaluation result') }}</h2>
                        <p class="text-xs text-zinc-600">{{ __('Evaluator: :name', ['name' => $request->evaluator?->name]) }}</p>
                    </div>

                    @if ($request->credentialType->requirements)
                        <div class="text-sm">
                            <p class="font-medium text-ink">{{ __('Requirements to check') }}</p>
                            <ul class="mt-1 list-disc ps-5 text-zinc-700">
                                <li>{{ __('Identity, student number, program and years attended') }}</li>
                                <li>{{ __('Graduation status and eligibility') }}</li>
                                @foreach ($request->credentialType->requirements as $requirement)
                                    <li>{{ $requirement }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form wire:submit="completeEvaluation" class="space-y-3">
                        <flux:textarea wire:model="evaluationRemarks" :label="__('Remarks')" badge="Optional" rows="2" />
                        <flux:button type="submit" variant="primary" icon="check" class="w-full">
                            {{ $request->requires_payment ? __('Requirements complete – send payment instructions') : __('Requirements complete – accept') }}
                        </flux:button>
                    </form>

                    <flux:modal.trigger name="mark-incomplete">
                        <flux:button icon="exclamation-triangle" class="w-full">{{ __('Requirements incomplete') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="request-action">
                        <flux:button icon="chat-bubble-left-ellipsis" class="w-full">{{ __('Ask the requester to clarify') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="decline-request">
                        <flux:button variant="danger" icon="no-symbol" class="w-full">{{ __('Not eligible – decline') }}</flux:button>
                    </flux:modal.trigger>
                </section>
            @endif

            @if ($status === S::ReturnedForCustomerAction)
                <section aria-labelledby="customer-heading" class="space-y-3 rounded-xl border border-amber-500 bg-amber-50 p-5">
                    <h2 id="customer-heading" class="text-lg font-semibold text-amber-900">{{ __('Waiting for the requester') }}</h2>
                    <p class="text-sm text-amber-950">{{ $request->accepted_at ? __('The processing period is paused. Record when the requester has complied.') : __('Record when the requester has clarified or complied, if they did so in person.') }}</p>
                    <form wire:submit="customerComplied" class="space-y-3">
                        <flux:textarea wire:model="compliedNotes" :label="__('Notes')" badge="Optional" rows="2" />
                        <flux:button type="submit" variant="primary" icon="play" class="w-full">{{ __('Requester complied – resume') }}</flux:button>
                    </form>
                </section>
            @endif

            @if ($status === S::PaymentVerification)
                <section aria-labelledby="payment-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5">
                    <h2 id="payment-heading" class="text-lg font-semibold text-primary">{{ __('Verify payment') }}</h2>
                    <p class="text-sm text-zinc-700">{{ __('Check the proof against the official payment record. Verifying accepts the request and starts the processing period.') }}</p>
                    @if ($pendingProof)
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-zinc-600">{{ __('Amount due') }}</dt><dd class="font-medium text-ink">₱{{ number_format((float) $request->amount_due, 2) }}</dd>
                            <dt class="text-zinc-600">{{ __('Amount paid') }}</dt><dd class="font-medium text-ink">{{ $pendingProof->amount !== null ? '₱'.number_format((float) $pendingProof->amount, 2) : '—' }}</dd>
                            <dt class="text-zinc-600">{{ __('Reference') }}</dt><dd class="font-medium text-ink">{{ $pendingProof->reference_no ?? '—' }}</dd>
                            <dt class="text-zinc-600">{{ __('Date paid') }}</dt><dd class="font-medium text-ink">{{ $pendingProof->paid_on?->format('M j, Y') ?? '—' }}</dd>
                        </dl>
                    @endif
                    <form wire:submit="verifyPayment" class="space-y-3">
                        <flux:input wire:model="officialReceiptNo" :label="__('Official receipt no.')" required />
                        <flux:textarea wire:model="paymentRemarks" :label="__('Remarks')" badge="Optional" rows="2" />
                        <flux:button type="submit" variant="primary" icon="check" class="w-full">{{ __('Verify payment & accept') }}</flux:button>
                    </form>
                    <flux:modal.trigger name="reject-payment">
                        <flux:button variant="danger" icon="x-mark" class="w-full">{{ __('Reject proof of payment') }}</flux:button>
                    </flux:modal.trigger>
                </section>
            @endif

            @if ($status === S::AwaitingPayment)
                <flux:callout icon="credit-card" color="amber">
                    <flux:callout.text>{{ __('Waiting for the requester to pay ₱:amount and upload the proof.', ['amount' => number_format((float) $request->amount_due, 2)]) }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($processing && $visit)
                <section aria-labelledby="current-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5">
                    <div>
                        <h2 id="current-heading" class="text-sm font-medium text-zinc-600">{{ __('Currently at') }}</h2>
                        <p class="text-lg font-semibold text-primary">{{ $visit->department->name }}</p>
                        <p class="text-xs text-zinc-600">{{ $visit->status->label() }} · {{ ($visit->received_at ?? $visit->forwarded_at)->diffForHumans() }}</p>
                    </div>

                    @include('partials.visit-actions', ['visit' => $visit, 'returnOffices' => $returnOffices])

                    @can('bypass', $visit)
                        <flux:modal.trigger name="bypass-step">
                            <flux:button variant="ghost" icon="forward" class="w-full">{{ __('Bypass this office') }}</flux:button>
                        </flux:modal.trigger>
                    @endcan
                </section>
            @endif

            @if ($processing && $status !== S::ReturnedForCorrection)
                <flux:modal.trigger name="request-action">
                    <flux:button variant="ghost" icon="pause-circle" class="w-full">{{ __('Return for customer action') }}</flux:button>
                </flux:modal.trigger>
            @endif

            @unless ($status->isFinal())
                @include('partials.delay-modal')
            @endunless

            @can('release', $request)
                <section aria-labelledby="release-heading" class="space-y-4 rounded-xl border border-green-700 bg-green-50 p-5">
                    <h2 id="release-heading" class="text-lg font-semibold text-green-900">{{ __('Release') }}</h2>
                    <form wire:submit="release" class="space-y-4">
                        <flux:radio.group wire:model.live="recipientType" :label="__('Claimed by')">
                            <flux:radio value="owner" :label="__('The document owner')" />
                            <flux:radio value="representative" :label="__('An authorized representative')" :disabled="$verifiedRepresentatives->isEmpty()" />
                        </flux:radio.group>
                        @if ($recipientType === 'representative')
                            <flux:select wire:model.live="recipientRepresentativeId" :label="__('Verified representative')" required>
                                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                @foreach ($verifiedRepresentatives as $representative)
                                    <flux:select.option :value="$representative->id">{{ $representative->full_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @elseif ($verifiedRepresentatives->isEmpty() && $request->representatives->isNotEmpty())
                            <flux:text size="sm">{{ __('A representative can claim only after their SPA and IDs are verified above.') }}</flux:text>
                        @endif
                        <flux:input wire:model="recipientName" :label="__('Name of recipient')" required />
                        <flux:input wire:model="idPresented" :label="__('ID presented')" :placeholder="__('e.g. Passport P1234567A')" required />
                        <flux:checkbox wire:model="identityChecked" :label="__('I checked the recipient\'s identity and authority')" />
                        <flux:checkbox wire:model="acknowledged" :label="__('The recipient acknowledged receipt (signed the release log)')" />
                        <flux:textarea wire:model="releaseRemarks" :label="__('Remarks')" badge="Optional" rows="2" />
                        <flux:button type="submit" variant="primary" icon="hand-raised" class="w-full">{{ __('Release credential') }}</flux:button>
                    </form>
                </section>
            @endcan

            @if ($request->release)
                <section aria-labelledby="released-heading" class="space-y-2 rounded-xl border border-secondary/50 p-5 text-sm">
                    <h2 id="released-heading" class="font-semibold text-primary">{{ __('Release record') }}</h2>
                    <p>{{ __(':name (:type)', ['name' => $request->release->recipient_name, 'type' => $request->release->recipient_type->label()]) }}</p>
                    <p class="text-zinc-600">{{ __('ID presented: :id', ['id' => $request->release->id_presented]) }}</p>
                    <p class="text-zinc-600">{{ __('Released :date by :name', ['date' => $request->release->released_at->format('M j, Y g:i A'), 'name' => $request->release->releaser?->name]) }}</p>
                </section>
            @endif

            @if ($request->steps->isNotEmpty())
                <section aria-labelledby="route-heading" class="rounded-xl border border-secondary/50 p-5">
                    <h2 id="route-heading" class="mb-4 font-semibold text-primary">{{ __('Route') }}</h2>
                    <x-request-route :steps="$request->steps" />
                </section>
            @endif

            @if ($request->accepted_at === null && ! $status->isFinal())
                <section aria-labelledby="college-heading" class="rounded-xl border border-secondary/50 p-5">
                    <h2 id="college-heading" class="font-semibold text-primary">{{ __('College') }}</h2>
                    <form wire:submit="changeCollege" class="mt-3 space-y-3">
                        <flux:select wire:model="collegeId" :label="__('Dean\'s college')" :description="__('Correct this if the requester\'s college is wrong.')">
                            @foreach ($colleges as $college)
                                <flux:select.option :value="$college->id">{{ $college->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button type="submit" size="sm">{{ __('Update college') }}</flux:button>
                    </form>
                </section>
            @endif
        </aside>
    </div>

    <flux:modal name="mark-incomplete" class="w-full md:w-[32rem]">
        <form wire:submit="markIncomplete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Requirements incomplete') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The requester is told exactly what is missing and that originals must be submitted in person or through an authorized representative.') }}</flux:text>
            </div>
            <flux:checkbox.group wire:model="missing" :label="__('Missing')">
                @foreach ($request->credentialType->requirements ?? [] as $requirement)
                    <flux:checkbox :value="$requirement" :label="$requirement" />
                @endforeach
            </flux:checkbox.group>
            <flux:input wire:model="otherMissing" :label="__('Other missing requirement')" :placeholder="__('e.g. Affidavit of loss')" />
            <flux:textarea wire:model="evaluationRemarks" :label="__('Remarks')" badge="Optional" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Notify requester') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="resolve-deficiency" class="w-full md:w-[30rem]">
        <form wire:submit="resolveDeficiency" class="space-y-6">
            <flux:heading size="lg">{{ __('Record compliance') }}</flux:heading>
            <flux:textarea wire:model="resolution" :label="__('What was received')" :description="__('The document received or the clearance status; your name and the date are recorded.')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Record') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="request-action" class="w-full md:w-[30rem]">
        <form wire:submit="requestAction" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $request->accepted_at ? __('Return for customer action') : __('Ask the requester to clarify') }}</flux:heading>
                <flux:text class="mt-2">{{ $request->accepted_at ? __('The processing period pauses until the requester acts; the original due date is kept and an adjusted one is set.') : __('The requester sees your message and can reply.') }}</flux:text>
            </div>
            <flux:textarea wire:model="actionMessage" :label="__('Message to the requester')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Send') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="decline-request" class="w-full md:w-[28rem]">
        <form wire:submit="decline" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Decline: not eligible') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The requester is notified with your reason. This cannot be undone.') }}</flux:text>
            </div>
            <flux:textarea wire:model="declineReason" :label="__('Reason')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Decline request') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @if ($idDocument)
        <flux:modal name="reject-identity" class="w-full md:w-[28rem]">
            <form wire:submit="rejectIdentity({{ $idDocument->id }})" class="space-y-6">
                <flux:heading size="lg">{{ __('Reject valid ID') }}</flux:heading>
                <flux:textarea wire:model="identityRemarks" :label="__('Reason')" :description="__('Sent to the requester, who can upload another ID.')" rows="3" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Reject ID') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <flux:modal name="reject-representative" class="w-full md:w-[28rem]">
        <form wire:submit="rejectRepresentative" class="space-y-6">
            <flux:heading size="lg">{{ __('Representative not accepted') }}</flux:heading>
            <flux:textarea wire:model="representativeRemarks" :label="__('Reason')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Reject') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @if ($visit)
        <flux:modal name="bypass-step" class="w-full md:w-[28rem]">
            <form wire:submit="bypass" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Bypass :office?', ['office' => $visit->department->name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('The document moves on to the next office. The reason is recorded and counted in the reports.') }}</flux:text>
                </div>
                <flux:textarea wire:model="bypassReason" :label="__('Reason')" rows="3" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Bypass') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <flux:modal name="reject-payment" class="w-full md:w-[28rem]">
        <form wire:submit="rejectPayment" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reject proof of payment') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The requester sees your remarks and can upload a valid proof.') }}</flux:text>
            </div>
            <flux:textarea wire:model="rejectRemarks" :label="__('Remarks')" :placeholder="__('e.g. Amount does not match; no matching transaction found')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Reject') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="cancel-request" class="w-full md:w-[28rem]">
        <form wire:submit="cancel" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel this request?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The requester will be notified with your reason. This cannot be undone.') }}</flux:text>
            </div>
            <flux:textarea wire:model="cancelReason" :label="__('Reason')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Cancel request') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
