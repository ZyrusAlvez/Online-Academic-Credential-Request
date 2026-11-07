@php
    use App\Enums\CheckItem as Item;
    use App\Enums\CheckResult;
    use App\Livewire\Registrar\Requests\Show as Page;

    $status = $request->status;
    $owner = $request->student;
    $idDocument = $request->identityDocument;
    $visit = $request->openVisit;

    [$queueLabel, $queueRoute] = match ($panel) {
        Page::PANEL_LOCKED, Page::PANEL_START, Page::PANEL_EVALUATION, Page::PANEL_RETURNED => [__('Initial Evaluation'), 'registrar.evaluation'],
        Page::PANEL_PAYMENT => [__('Payment Validation'), 'registrar.payments'],
        Page::PANEL_FINAL_CHECK => [__('Final Quality Check'), 'registrar.final-check'],
        Page::PANEL_RELEASE => [__('Release'), 'registrar.release'],
        default => [__('Records'), 'registrar.records'],
    };

    // The decision applies only when every part shown is confirmed (REQ-16-12, 17, 19).
    $allConfirmed = collect($items)->every(fn ($item) => ($results[$item->value] ?? null) === CheckResult::Confirmed);
    $anyRejected = collect($items)->contains(fn ($item) => ($results[$item->value] ?? null) === CheckResult::Rejected);
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route($queueRoute)" wire:navigate>{{ $queueLabel }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if ($panel === Page::PANEL_LOCKED)
        {{-- Another registrar owns the evaluation (REQ-16-09). --}}
        <flux:callout icon="lock-closed" color="amber" data-evaluation-locked>
            <flux:callout.heading>{{ __(':ref is under evaluation by :name.', ['ref' => $request->reference_no, 'name' => $request->evaluator?->name]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('It opens again once they approve, return or decline it.') }}</flux:callout.text>
        </flux:callout>
        <flux:button :href="route('registrar.evaluation')" wire:navigate icon="arrow-left">{{ __('Back to Initial Evaluation') }}</flux:button>
    @else
        <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$request->requester_name">
            <x-slot:afterTitle>
                <x-copy-button :value="$request->reference_no" :label="__('Copy tracking number')" :copied="__('Tracking number copied.')" />
            </x-slot:afterTitle>
            <x-slot:actions>
                <x-request-status :status="$status" class="self-center" />
                @if ($request->isOverdue())
                    <flux:badge color="red" icon="clock" class="self-center">{{ __('Overdue since :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if ($panel === Page::PANEL_START)
            <section aria-labelledby="start-heading" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6">
                <div>
                    <h2 id="start-heading" class="text-lg font-semibold text-primary">{{ __('Initial Evaluation') }}</h2>
                    <p class="text-sm text-zinc-700">{{ __('Received :time. Once you start, only you can evaluate it until you decide.', ['time' => $request->submitted_at->diffForHumans()]) }}</p>
                </div>
                <flux:button wire:click="startEvaluation" variant="primary" icon="magnifying-glass">{{ __('Start evaluation') }}</flux:button>
            </section>
        @endif

        @if ($panel === Page::PANEL_RETURNED)
            <section aria-labelledby="returned-heading" class="space-y-3 rounded-xl border border-amber-500 bg-amber-50 p-5 sm:p-6">
                <h2 id="returned-heading" class="text-lg font-semibold text-amber-900">{{ __('Returned to the requester') }}</h2>
                @if ($lastReturn)
                    <p class="text-sm whitespace-pre-line text-amber-950">{{ $lastReturn->remarks }}</p>
                    <p class="text-xs text-amber-900">{{ __(':date · :name', ['date' => $lastReturn->created_at->format('M j, Y g:i A'), 'name' => $lastReturn->actor?->name]) }}</p>
                @endif
                <p class="text-sm text-amber-950">{{ __('It comes back here when the requester resubmits. If they complied in person, reopen the evaluation.') }}</p>
                <flux:button wire:click="reopenEvaluation" variant="primary" icon="arrow-path">{{ __('Reopen evaluation') }}</flux:button>
            </section>
        @endif

        @if ($panel === Page::PANEL_EVALUATION)
            {{-- The evaluation checklist (REQ-16-10). --}}
            @foreach ($items as $item)
                <x-check-card :item="$item" :result="$results[$item->value] ?? null" wire:key="check-{{ $item->value }}">
                    @switch($item)
                        @case(Item::CredentialRequest)
                            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                <div><dt class="text-zinc-500">{{ __('Credential') }}</dt><dd class="font-medium text-ink">{{ $request->credentialType->name }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Purpose') }}</dt><dd class="font-medium text-ink">{{ $request->purpose }}</dd></div>
                                @if ($request->receiving_institution)
                                    <div class="sm:col-span-2"><dt class="text-zinc-500">{{ __('Receiving school') }}</dt><dd class="font-medium text-ink">{{ $request->receiving_institution }}</dd></div>
                                @endif
                                <div><dt class="text-zinc-500">{{ __('Amount due') }}</dt><dd class="font-medium text-ink">{{ $request->requires_payment ? '₱'.number_format((float) $request->amount_due, 2) : __('None required') }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Transacting') }}</dt><dd class="font-medium text-ink">{{ $request->requester_mode->label() }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Submitted') }}</dt><dd class="font-medium text-ink">{{ $request->submitted_at?->format('M j, Y g:i A') }}</dd></div>
                            </dl>
                            @if ($request->credentialType->requirements)
                                <p class="mt-3 text-xs text-zinc-600">{{ __('Documentary requirements: :list', ['list' => implode('; ', $request->credentialType->requirements)]) }}</p>
                            @endif
                            @break

                        @case(Item::Identity)
                            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                <div class="sm:col-span-2"><dt class="text-zinc-500">{{ __('Name') }}</dt><dd class="font-medium break-words text-ink">{{ $request->requester_name }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Email') }}</dt><dd class="font-medium break-all text-ink"><a href="mailto:{{ $owner->email }}" class="text-highlight underline underline-offset-2">{{ $owner->email }}</a></dd></div>
                                <div><dt class="text-zinc-500">{{ __('Contact number') }}</dt><dd class="font-medium text-ink">{{ $request->contact_number ?? '—' }}</dd></div>
                                <div class="sm:col-span-2">
                                    <dt class="text-zinc-500">{{ __('Valid ID') }}</dt>
                                    <dd class="font-medium text-ink">
                                        @if ($idDocument)
                                            <x-file-link :href="route('identity-documents.show', $idDocument)" :name="$idDocument->id_type.' – '.$request->requester_name" :type="$idDocument->mime_type">
                                                {{ __('Open :type', ['type' => $idDocument->id_type]) }}
                                            </x-file-link>
                                            <span class="font-normal text-zinc-500">· {{ __('uploaded :date', ['date' => $idDocument->created_at->format('M j, Y')]) }}</span>
                                        @else
                                            {{ __('No valid ID was sent with this request.') }}
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                            @break

                        @case(Item::AcademicRecord)
                            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                <div><dt class="text-zinc-500">{{ __('Status') }}</dt><dd class="font-medium text-ink">{{ $request->academic_status->label() }} · {{ $request->attended_from }}–{{ $request->attended_to }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Student number') }}</dt><dd class="font-medium text-ink">{{ $request->student_number ?? '—' }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('College') }}</dt><dd class="font-medium text-ink">{{ $request->college->name }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Program or strand') }}</dt><dd class="font-medium text-ink">{{ $request->program }}</dd></div>
                            </dl>
                            @break

                        @case(Item::Representative)
                            @if ($request->representatives->isEmpty())
                                <p class="text-sm text-zinc-600">{{ __('No representative was named.') }}</p>
                            @else
                                <x-representative-list :representatives="$request->representatives" />
                            @endif
                            @break
                    @endswitch
                </x-check-card>
            @endforeach
        @endif

        @if ($panel === Page::PANEL_PAYMENT)
            {{-- The payment validation checklist (REQ-16-17). --}}
            @foreach ($items as $item)
                <x-check-card :item="$item" :result="$results[$item->value] ?? null" wire:key="check-{{ $item->value }}">
                    @if ($item === Item::PaymentProof)
                        @if ($pendingProof)
                            @if ($pendingProof->isImage())
                                <x-file-link :href="route('payment-proofs.show', $pendingProof)" :name="__('Proof of payment: :file', ['file' => $pendingProof->original_name])" :type="$pendingProof->mime_type" class="block w-fit cursor-zoom-in">
                                    <x-loading-image :src="route('payment-proofs.show', $pendingProof)" :alt="__('Proof of payment uploaded by the requester (open larger)')" class="max-h-96 w-auto rounded-lg border border-secondary/50" />
                                </x-file-link>
                            @else
                                <x-file-link :href="route('payment-proofs.show', $pendingProof)" :name="__('Proof of payment: :file', ['file' => $pendingProof->original_name])" :type="$pendingProof->mime_type" class="text-sm font-medium">
                                    {{ $pendingProof->original_name }}
                                </x-file-link>
                            @endif
                        @else
                            <p class="text-sm text-zinc-600">{{ __('No proof of payment uploaded yet.') }}</p>
                        @endif
                    @else
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="text-zinc-500">{{ __('Amount due') }}</dt><dd class="font-medium text-ink">₱{{ number_format((float) $request->amount_due, 2) }}</dd></div>
                            <div><dt class="text-zinc-500">{{ __('Amount paid') }}</dt><dd class="font-medium text-ink">{{ $pendingProof?->amount !== null ? '₱'.number_format((float) $pendingProof->amount, 2) : '—' }}</dd></div>
                            <div><dt class="text-zinc-500">{{ __('Reference') }}</dt><dd class="min-w-0 font-medium break-all text-ink">{{ $pendingProof?->reference_no ?? '—' }}</dd></div>
                            <div><dt class="text-zinc-500">{{ __('Date paid') }}</dt><dd class="font-medium text-ink">{{ $pendingProof?->paid_on?->format('M j, Y') ?? '—' }}</dd></div>
                        </dl>
                    @endif
                </x-check-card>
            @endforeach
        @endif

        @if ($panel === Page::PANEL_FINAL_CHECK)
            @if ($visit->status === \App\Enums\VisitStatus::InTransit)
                <section aria-labelledby="receive-heading" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6">
                    <div>
                        <h2 id="receive-heading" class="text-lg font-semibold text-primary">{{ __('Final Quality Check') }}</h2>
                        <p class="text-sm text-zinc-700">{{ __('Forwarded :time from :office. Accept the receipt once the document is in your office.', ['time' => $visit->forwarded_at->diffForHumans(), 'office' => $request->visits->where('status', \App\Enums\VisitStatus::Completed)->last()?->department->name ?? __('the last office')]) }}</p>
                    </div>
                    <flux:button wire:click="acceptReceipt" variant="primary" icon="inbox-arrow-down">{{ __('Accept receipt') }}</flux:button>
                </section>
            @else
                {{-- The final quality check checklist (REQ-16-19). --}}
                @foreach ($items as $item)
                    <x-check-card :item="$item" :result="$results[$item->value] ?? null" wire:key="check-{{ $item->value }}">
                        @if ($item === Item::CredentialDocument)
                            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                                <div><dt class="text-zinc-500">{{ __('Credential') }}</dt><dd class="font-medium text-ink">{{ $request->credentialType->name }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Issued to') }}</dt><dd class="font-medium break-words text-ink">{{ $request->requester_name }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('Purpose') }}</dt><dd class="font-medium text-ink">{{ $request->purpose }}</dd></div>
                                @if ($request->receiving_institution)
                                    <div><dt class="text-zinc-500">{{ __('Receiving school') }}</dt><dd class="font-medium text-ink">{{ $request->receiving_institution }}</dd></div>
                                @endif
                            </dl>
                        @else
                            <x-request-route :steps="$request->steps" />
                        @endif
                    </x-check-card>
                @endforeach
            @endif
        @endif

        @if (in_array($panel, [Page::PANEL_EVALUATION, Page::PANEL_PAYMENT], true) || ($panel === Page::PANEL_FINAL_CHECK && $visit->status !== \App\Enums\VisitStatus::InTransit))
            {{-- The stage's decision (REQ-16-11). --}}
            <section aria-labelledby="decision-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6" data-decision>
                <div>
                    <h2 id="decision-heading" class="text-lg font-semibold text-primary">{{ __('Decision') }}</h2>
                    <p class="text-sm text-zinc-700">
                        @if ($allConfirmed)
                            {{ __('Every part is confirmed.') }}
                        @elseif ($anyRejected)
                            {{ __('Some parts are rejected: return or decline, with remarks.') }}
                        @else
                            {{ __('Confirm every part to approve.') }}
                        @endif
                    </p>
                    @if ($panel === Page::PANEL_EVALUATION)
                        <p class="text-xs text-zinc-600">{{ __('Evaluator: :name', ['name' => $request->evaluator?->name]) }}</p>
                    @endif
                </div>

                @if ($panel === Page::PANEL_PAYMENT)
                    <flux:input wire:model="officialReceiptNo" :label="__('Official receipt no.')" :description="__('Needed to approve.')" />
                @endif
                <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="3" />

                {{-- Always enabled; a decision that can't run says why (REQ-17-17). The approving one is on the right (REQ-17-02). --}}
                <div class="flex flex-wrap justify-center gap-2" data-decision-buttons>
                    <flux:button wire:click="decline" variant="danger" icon="no-symbol" data-decline-button data-confirm-title="{{ __('Decline request') }}" data-confirm-label="{{ __('Decline') }}" :wire:confirm="__('Decline :ref? The requester is notified with your remarks. This cannot be undone.', ['ref' => $request->reference_no])">{{ __('Decline') }}</flux:button>
                    @if ($panel === Page::PANEL_EVALUATION)
                        <flux:button wire:click="returnRequest" icon="arrow-uturn-left" data-return-button>{{ __('Return to requester') }}</flux:button>
                    @elseif ($panel === Page::PANEL_PAYMENT)
                        <flux:button wire:click="returnRequest" icon="arrow-uturn-left" data-return-button>{{ __('Return') }}</flux:button>
                    @endif
                    <flux:button wire:click="approve" variant="primary" icon="check" data-approve-button>{{ __('Approve') }}</flux:button>
                </div>
            </section>
        @endif

        @if ($panel === Page::PANEL_RELEASE)
            @if ($request->representatives->contains(fn ($r) => $r->status === \App\Enums\ReviewStatus::Pending))
                {{-- A representative declared after the evaluation (REQ-16-20). --}}
                <section aria-labelledby="claimants-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="claimants-heading" class="text-lg font-semibold text-primary">{{ __('Authorized representatives') }}</h2>
                    <x-representative-list :representatives="$request->representatives" reviewable />
                </section>
            @endif

            <section aria-labelledby="release-heading" class="space-y-4 rounded-xl border border-green-700 bg-green-50 p-5 sm:p-6">
                <h2 id="release-heading" class="text-lg font-semibold text-green-900">{{ __('Release') }}</h2>
                <form wire:submit="release" class="space-y-4">
                    <flux:radio.group wire:model.live="recipientType" :label="__('Claimed by')">
                        <flux:radio value="owner" :label="__('The document owner: :name', ['name' => $request->requester_name])" />
                        <flux:radio value="representative" :label="__('A verified representative')" :disabled="$verifiedRepresentatives->isEmpty()" />
                    </flux:radio.group>
                    @if ($recipientType === 'representative')
                        <flux:select wire:model="recipientRepresentativeId" :label="__('Verified representative')" required>
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($verifiedRepresentatives as $representative)
                                <flux:select.option :value="$representative->id">{{ $representative->full_name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    <flux:button type="submit" variant="primary" icon="hand-raised">{{ __('Mark released') }}</flux:button>
                </form>
            </section>
        @endif

        @if ($panel === Page::PANEL_OFFICE)
            {{-- The Office of the Registrar's own route step: preparation or a correction (spec 05). --}}
            @php($nextOffice = $request->nextOffice())
            <section aria-labelledby="current-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6" data-office-panel>
                {{-- Where the paper is and where it goes next (REQ-17-13). --}}
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt id="current-heading" class="text-sm font-medium text-zinc-600">{{ __('Currently at') }}</dt>
                        {{-- A forwarded paper stays with its sender until it is received (REQ-17-18). --}}
                        <dd class="text-lg font-semibold text-primary" data-holder>{{ $visit->holder()->name }}</dd>
                        <dd class="text-xs text-zinc-600">
                            @if ($visit->status === \App\Enums\VisitStatus::InTransit)
                                {{ __('Forwarded :time to :office, not yet received', ['time' => $visit->forwarded_at->diffForHumans(), 'office' => $visit->department->name]) }}
                            @else
                                {{ __($visit->status->label()) }} · {{ ($visit->done_at ?? $visit->received_at)->diffForHumans() }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-zinc-600">{{ __('Next') }}</dt>
                        <dd class="text-lg font-semibold text-ink" data-next-office>{{ $nextOffice?->name ?? __('Release') }}</dd>
                    </div>
                </dl>
                @include('partials.visit-actions', ['visit' => $visit, 'returnOffices' => $returnOffices])
            </section>
        @endif

        {{-- The ledger is on the Records page only (REQ-17-04). --}}
        @include('partials.request-record', ['hide' => ['ledger', ...match ($panel) {
            Page::PANEL_EVALUATION => ['details', 'representatives'],
            Page::PANEL_FINAL_CHECK => $visit->status === \App\Enums\VisitStatus::InTransit ? [] : ['route'],
            default => [],
        }]])
    @endif

    {{-- Why a decision could not run (REQ-17-17). --}}
    <flux:modal name="decision-blocked" class="w-full md:w-[28rem]" data-decision-blocked>
        <div class="space-y-4">
            <flux:heading size="lg">
                {{ match ($blockedAction) {
                    'approve' => __("Can't approve yet"),
                    'return' => __("Can't return yet"),
                    'decline' => __("Can't decline yet"),
                    default => __("Can't do that yet"),
                } }}
            </flux:heading>

            @if ($blockedParts !== [])
                {{-- Approve: the rule, what blocks it, and how to approve; never Return or Decline. --}}
                {{-- One-line PHP directives only: a PHP block here would pair with the one-line directive above and swallow the page. --}}
                @php($toConfirm = collect($blockedParts)->where('state', '!==', 'missing')->count())
                @php($receiptMissing = collect($blockedParts)->contains('state', 'missing'))
                <p class="text-sm text-ink">
                    {{ $panel === Page::PANEL_PAYMENT
                        ? __('Approve needs every part of the checklist confirmed and the official receipt number.')
                        : __('Approve needs every part of the checklist confirmed.') }}
                </p>
                <ul class="space-y-2 text-sm text-ink" data-blocked-parts>
                    @foreach ($blockedParts as $part)
                        <li class="flex items-start gap-2" data-part-state="{{ $part['state'] }}">
                            @if ($part['state'] === 'rejected')
                                <flux:icon.x-mark variant="micro" class="mt-0.5 shrink-0 text-red-700" aria-hidden="true" />
                            @else
                                <flux:icon.minus variant="micro" class="mt-0.5 shrink-0 text-zinc-500" aria-hidden="true" />
                            @endif
                            <span>
                                <span class="font-medium">{{ __($part['label']) }}</span>
                                — {{ match ($part['state']) {
                                    'rejected' => __('rejected'),
                                    'missing' => __('not entered yet'),
                                    default => __('not checked yet'),
                                } }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="text-sm text-ink" data-blocked-hint>
                    @if ($toConfirm > 0 && $receiptMissing)
                        {{ __('To approve, change the checklist parts to Confirm and enter the official receipt number.') }}
                    @elseif ($toConfirm > 0)
                        {{ $toConfirm === 1 ? __('To approve, change it to Confirm.') : __('To approve, change these to Confirm.') }}
                    @else
                        {{ __('To approve, enter the official receipt number.') }}
                    @endif
                </p>
            @endif

            @if ($blockers !== [])
                <ul class="list-disc space-y-2 ps-5 text-sm text-ink" data-blockers>
                    @foreach ($blockers as $blocker)
                        <li>{{ __($blocker) }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="flex justify-end">
                <flux:modal.close><flux:button variant="primary">{{ __('OK') }}</flux:button></flux:modal.close>
            </div>
        </div>
    </flux:modal>

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
</div>
