@php
    use App\Enums\CheckItem as Item;
    use App\Enums\CheckResult;
    use App\Livewire\Registrar\Requests\Show as Page;

    $status = $request->status;
    $idDocument = $request->identityDocument;
    $visit = $request->openVisit;

    // The queue the page is under is the breadcrumb (REQ-16-26).
    [$queueLabel, $queueRoute] = $currentQueue !== null ? [__($currentQueue->label()), $currentQueue->routeName()] : [__('Records'), 'records'];

    // The decision applies only when every part shown is confirmed (REQ-16-12, 17).
    $allConfirmed = collect($items)->every(fn ($item) => ($results[$item->value] ?? null) === CheckResult::Confirmed);
    $anyRejected = collect($items)->contains(fn ($item) => ($results[$item->value] ?? null) === CheckResult::Rejected);
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    <flux:breadcrumbs>
        {{-- Back to the list as it was left (REQ-16-26). --}}
        <flux:breadcrumbs.item :href="$listUrl ?: route($queueRoute)" wire:navigate>{{ $queueLabel }}</flux:breadcrumbs.item>
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
                <x-walk-in-badge :request="$request" class="self-center" />
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
                    <p class="text-sm text-zinc-700">{{ __('Received :time.', ['time' => $request->submitted_at->diffForHumans()]) }}</p>
                </div>
                <flux:button wire:click="startEvaluation" variant="primary" icon="magnifying-glass">{{ __('Start evaluation') }}</flux:button>
            </section>
        @endif

        @if ($panel === Page::PANEL_CLARIFICATION)
            {{-- Returned for clarification: the requester corrects it online (REQ-20-03, 05). --}}
            <section aria-labelledby="clarification-heading" class="space-y-3 rounded-xl border border-amber-500 bg-amber-50 p-5 sm:p-6" data-clarification-panel>
                <h2 id="clarification-heading" class="text-lg font-semibold text-amber-900">{{ __('Returned for Clarification') }}</h2>
                @if ($lastReturn)
                    <p class="text-sm whitespace-pre-line text-amber-950">{{ $lastReturn->displayRemarks() }}</p>
                    <p class="text-xs text-amber-900">{{ __(':date · :name', ['date' => $lastReturn->created_at->format('M j, Y g:i A'), 'name' => $lastReturn->actor?->name]) }}</p>
                @endif
            </section>
        @endif

        @if ($panel === Page::PANEL_COMPLIANCE)
            {{-- Office visit: each required item is marked received, then compliance is confirmed (REQ-20-09). --}}
            @php($outstanding = $request->deficiencies->whereNull('resolved_at')->count())
            {{-- The ledger line is "Required: <items>\n<remarks>"; the items are listed below, so only the remarks show here. --}}
            @php($visitRemarks = $lastReturn && str_contains((string) $lastReturn->remarks, "\n") ? \Illuminate\Support\Str::after((string) $lastReturn->remarks, "\n") : '')
            <section aria-labelledby="compliance-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6" data-compliance-panel>
                <div>
                    <h2 id="compliance-heading" class="text-lg font-semibold text-primary">{{ __('Office Visit') }}</h2>
                    <p class="text-sm text-zinc-700">{{ __('Mark each item received, then confirm compliance.') }}</p>
                </div>
                @if ($visitRemarks !== '')
                    <p class="rounded-md bg-white p-3 text-sm whitespace-pre-line text-ink" data-visit-remarks><span class="font-semibold">{{ __('Remarks:') }}</span> {{ $visitRemarks }}</p>
                @endif
                <ul class="divide-y divide-secondary/40 rounded-lg border border-secondary/50 bg-white" data-required-items>
                    @foreach ($request->deficiencies as $deficiency)
                        <li wire:key="required-{{ $deficiency->id }}" class="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                            <span class="font-medium text-ink">{{ $deficiency->description }}</span>
                            @if ($deficiency->resolved_at)
                                {{-- Always on its own line at the lower right, however long the name (REQ-20-09). --}}
                                <span class="flex w-full items-center justify-end gap-1 text-green-800" data-received>
                                    <flux:icon.check-circle variant="mini" aria-hidden="true" />
                                    {{ __('Received :date · :name', ['date' => $deficiency->resolved_at->format('M j, Y g:i A'), 'name' => $deficiency->resolver?->name]) }}
                                </span>
                            @else
                                <flux:button size="sm" wire:click="markReceived({{ $deficiency->id }})" icon="inbox-arrow-down" data-mark-received>{{ __('Mark Received') }}</flux:button>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <flux:textarea wire:model="remarks" :label="__('Remarks')" badge="Optional" rows="3" data-compliance-remarks />
                <div class="flex flex-wrap justify-center gap-2" data-decision-buttons>
                    <flux:button wire:click="decline" variant="danger" icon="no-symbol" data-decline-button data-confirm-title="{{ __('Decline request') }}" data-confirm-label="{{ __('Decline') }}" :wire:confirm="__('Decline :ref? The requester is notified with your remarks. This cannot be undone.', ['ref' => $request->reference_no])">{{ __('Decline') }}</flux:button>
                    {{-- Not until every item is received; the server refuses it too (REQ-20-09). --}}
                    <flux:button wire:click="confirmCompliance" variant="primary" icon="check" :disabled="$outstanding > 0" data-confirm-compliance>{{ __('Confirm Compliance') }}</flux:button>
                </div>
                @if ($outstanding > 0)
                    <p class="text-center text-xs text-zinc-600">{{ trans_choice(':count requirement still to receive.|:count requirements still to receive.', $outstanding) }}</p>
                @endif
            </section>
        @endif

        @if ($panel === Page::PANEL_EVALUATION)
            {{-- The evaluation checklist (REQ-16-10); each part's data as summary rows (REQ-16-24). --}}
            @foreach ($items as $item)
                <x-check-card :item="$item" :result="$results[$item->value] ?? null" :carried="$carried[$item->value] ?? null" wire:key="check-{{ $item->value }}">
                    @switch($item)
                        @case(Item::CredentialRequest)
                            {{-- Every credential of the application (REQ-27-09). --}}
                            @if ($credentials->count() > 1)
                                <x-application-credentials :requests="$credentials" class="mb-3" />
                            @endif
                            <dl class="divide-y divide-zinc-200 text-sm">
                                @if ($credentials->count() <= 1)
                                    <x-summary-row :label="__('Credential')">{{ $request->credentialType->name }}</x-summary-row>
                                    <x-summary-row :label="__('Purpose')">{{ $request->purpose }}</x-summary-row>
                                    @if ($request->receiving_institution)
                                        <x-summary-row :label="__('Receiving school')">{{ $request->receiving_institution }}</x-summary-row>
                                    @endif
                                    @if ($request->academic_year)
                                        <x-summary-row :label="__('Terms')">{{ \App\Enums\Term::labels($request->terms) }}</x-summary-row>
                                        <x-summary-row :label="__('Academic year')">{{ $request->academic_year }}</x-summary-row>
                                    @endif
                                @endif
                                <x-summary-row :label="__('Amount due')">{{ $request->requires_payment ? '₱'.number_format((float) $request->amount_due, 2) : __('None required') }}</x-summary-row>
                                <x-summary-row :label="__('Transacting')">{{ $request->requester_mode->label() }}</x-summary-row>
                                <x-summary-row :label="__('Submitted')">{{ $request->submitted_at?->format('M j, Y g:i A') }}</x-summary-row>
                            </dl>
                            @break

                        @case(Item::Identity)
                            <dl class="divide-y divide-zinc-200 text-sm">
                                <x-summary-row :label="__('Name')">{{ $request->requester_name }}</x-summary-row>
                                @if ($request->sex)
                                    <x-summary-row :label="__('Sex')">{{ $request->sex->label() }}</x-summary-row>
                                @endif
                                <x-summary-row :label="__('Email')" class="[&_dd]:break-all"><a href="mailto:{{ $request->requesterEmail() }}" class="text-highlight underline underline-offset-2">{{ $request->requesterEmail() }}</a></x-summary-row>
                                <x-summary-row :label="__('Contact number')">{{ $request->contact_number ?? '—' }}</x-summary-row>
                                <x-summary-row :label="__('Valid ID')">
                                    @if ($idDocument)
                                        <x-file-link :href="route('identity-documents.show', $idDocument)" :name="$idDocument->id_type.' – '.$request->requester_name" :type="$idDocument->mime_type">
                                            {{ __('Open :type', ['type' => $idDocument->id_type]) }}
                                        </x-file-link>
                                        <span class="font-normal text-zinc-500">· {{ __('uploaded :date', ['date' => $idDocument->created_at->format('M j, Y')]) }}</span>
                                    @else
                                        {{ __('No valid ID was sent with this request.') }}
                                    @endif
                                </x-summary-row>
                            </dl>
                            @break

                        @case(Item::AcademicRecord)
                            <dl class="divide-y divide-zinc-200 text-sm">
                                <x-summary-row :label="__('Status')">{{ $request->academic_status->label() }} · {{ $request->attended_from }}–{{ $request->attended_to }}</x-summary-row>
                                <x-summary-row :label="__('Student number')">{{ $request->student_number ?? '—' }}</x-summary-row>
                                <x-summary-row :label="__('College')">{{ $request->college->name }}</x-summary-row>
                                <x-summary-row :label="__('Program or strand')">{{ $request->program }}</x-summary-row>
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

        @if ($panel === Page::PANEL_FINAL_CHECK)
            {{-- The final quality check: the credential and its route, read-only (REQ-16-19). Received at
                 once when the Registrar brings it back (REQ-32-09), so there is no Accept receipt. --}}
            <section aria-labelledby="final-document-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6" data-final-check-details>
                <h2 id="final-document-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Credential Document') }}</h2>
                <dl class="divide-y divide-zinc-200 text-sm">
                    <x-summary-row :label="__('Credential')">{{ $request->credentialType->name }}</x-summary-row>
                    <x-summary-row :label="__('Issued to')">{{ $request->requester_name }}</x-summary-row>
                    <x-summary-row :label="__('Purpose')">{{ $request->purpose }}</x-summary-row>
                    @if ($request->receiving_institution)
                        <x-summary-row :label="__('Receiving school')">{{ $request->receiving_institution }}</x-summary-row>
                    @endif
                </dl>
            </section>
            <section aria-labelledby="final-route-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6" data-final-check-route>
                <h2 id="final-route-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Signatures and Stamps') }}</h2>
                <x-request-route :steps="$request->steps" />
            </section>
        @endif

        @if ($panel === Page::PANEL_RELEASE)
            @if ($request->representatives->contains(fn ($r) => $r->status === \App\Enums\ReviewStatus::Pending))
                {{-- A representative declared after the evaluation (REQ-16-20). --}}
                <section aria-labelledby="claimants-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="claimants-heading" class="text-lg font-semibold text-primary">{{ __('Authorized Representatives') }}</h2>
                    <x-representative-list :representatives="$request->representatives" reviewable />
                </section>
            @endif

            <section aria-labelledby="release-heading" class="space-y-4 rounded-xl border border-green-700 bg-green-50 p-5 sm:p-6">
                <h2 id="release-heading" class="text-lg font-semibold text-green-900">{{ __('Release') }}</h2>
                <form wire:submit="release" class="space-y-4">
                    @include('partials.release-recipient')
                    <flux:button type="submit" variant="primary" icon="hand-raised">{{ __('Mark released') }}</flux:button>
                </form>
            </section>
        @endif

        {{-- The ledger is on the Records page only (REQ-17-04). --}}
        @include('partials.request-record', ['hide' => ['ledger', ...match ($panel) {
            Page::PANEL_EVALUATION => ['details', 'representatives'],
            Page::PANEL_COMPLIANCE => ['deficiencies'],
            // The proof under review is shown above; the list would repeat it (REQ-16-17).
            Page::PANEL_PAYMENT => ['proofs'],
            Page::PANEL_FINAL_CHECK => $visit->status === \App\Enums\VisitStatus::InTransit ? [] : ['route'],
            default => [],
        }]])

        @if (in_array($panel, [Page::PANEL_EVALUATION, Page::PANEL_PAYMENT], true) || ($panel === Page::PANEL_FINAL_CHECK && $visit->status !== \App\Enums\VisitStatus::InTransit))
            {{-- What the requester wrote when resubmitting, just above the Decision (REQ-20-14). --}}
            @if ($resubmitNote !== '')
                <p class="rounded-md border border-secondary/50 bg-white p-3 text-sm whitespace-pre-line text-ink" data-resubmit-note><span class="font-semibold">{{ __('Requester\'s note:') }}</span> {{ $resubmitNote }}</p>
            @endif

            {{-- The stage's decision (REQ-16-11), last on the page (REQ-16-27). --}}
            <section aria-labelledby="decision-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6" data-decision>
                <div>
                    <h2 id="decision-heading" class="text-lg font-semibold text-primary">{{ __('Decision') }}</h2>
                    @if ($panel === Page::PANEL_EVALUATION)
                        <p class="text-sm text-zinc-700">
                            @if ($allConfirmed)
                                {{ __('Every part is confirmed.') }}
                            @elseif ($anyRejected)
                                {{ __('Some parts are rejected.') }}
                            @else
                                {{ __('Confirm every part to approve.') }}
                            @endif
                        </p>
                        <p class="text-xs text-zinc-600">{{ __('Evaluator: :name', ['name' => $request->evaluator?->name]) }}</p>
                    @endif
                </div>

                @if ($panel === Page::PANEL_PAYMENT)
                    {{-- A walk-in payment, made at the counter: its official receipt (REQ-26-05), for
                         every credential of the application (REQ-27-09). --}}
                    @if ($credentials->count() > 1)
                        <x-application-credentials :requests="$credentials" :total="$request->amount_due" />
                    @endif
                    <dl class="divide-y divide-zinc-200 text-sm" data-walk-in-payment>
                        <x-summary-row :label="__('Amount due')">₱{{ number_format((float) $request->amount_due, 2) }}</x-summary-row>
                    </dl>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:input wire:model="receiptNo" :label="__('Official receipt no.')" maxlength="50" required data-receipt-no-input />
                        <flux:input wire:model="amountPaid" type="number" step="0.01" min="0.01" inputmode="decimal" :label="__('Amount paid')" required data-amount-paid-input />
                    </div>
                    <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" />
                @else
                    <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="3" />
                @endif

                {{-- Always enabled; a decision that can't run says why (REQ-17-17). The approving one is on the right (REQ-17-02). --}}
                <div class="flex flex-wrap justify-center gap-2" data-decision-buttons>
                    <flux:button wire:click="decline" variant="danger" icon="no-symbol" data-decline-button data-confirm-title="{{ __('Decline request') }}" data-confirm-label="{{ __('Decline') }}" :wire:confirm="__('Decline :ref? The requester is notified with your remarks. This cannot be undone.', ['ref' => $request->reference_no])">{{ __('Decline') }}</flux:button>
                    @if ($panel === Page::PANEL_EVALUATION)
                        {{-- The four evaluation decisions (REQ-20-02). --}}
                        <flux:modal.trigger name="office-visit">
                            <flux:button icon="building-office" data-office-visit-button>{{ __('Require Office Visit') }}</flux:button>
                        </flux:modal.trigger>
                        <flux:button wire:click="returnRequest" icon="arrow-uturn-left" data-return-button>{{ __('Return for Clarification') }}</flux:button>
                    @endif
                    @if ($panel === Page::PANEL_PAYMENT)
                        <flux:button wire:click="validatePayment" variant="primary" icon="check" data-validate-payment-button>{{ __('Validate Payment') }}</flux:button>
                    @else
                        <flux:button wire:click="approve" variant="primary" icon="check" data-approve-button>{{ __('Approve') }}</flux:button>
                    @endif
                </div>
            </section>
        @endif
    @endif

    {{-- Why a decision could not run (REQ-17-17). --}}
    <flux:modal name="decision-blocked" class="w-full md:w-[28rem]" data-decision-blocked>
        <div class="space-y-4">
            <flux:heading size="lg">
                {{ match ($blockedAction) {
                    'approve' => __("Can't approve yet"),
                    'return' => $panel === Page::PANEL_EVALUATION ? __("Can't return for clarification yet") : __("Can't return to the requester yet"),
                    'confirm' => __("Can't confirm compliance yet"),
                    'decline' => __("Can't decline yet"),
                    default => __("Can't do that yet"),
                } }}
            </flux:heading>

            @if ($blockedAction === 'confirm' && $blockedParts !== [])
                {{-- Confirm Compliance: the items still to receive (REQ-20-09). --}}
                <p class="text-sm text-ink">{{ __('Still to receive:') }}</p>
                <ul class="list-disc space-y-1 ps-5 text-sm text-ink" data-blocked-parts>
                    @foreach ($blockedParts as $part)
                        <li>{{ $part['label'] }}</li>
                    @endforeach
                </ul>
                <p class="text-sm text-ink" data-blocked-hint>{{ __('Mark each one received when the original is submitted.') }}</p>
            @elseif ($blockedParts !== [])
                {{-- Approve at the Initial Evaluation: the rule, what blocks it, and how to approve;
                     never Return or Decline. --}}
                <p class="text-sm text-ink">{{ __('Approve needs every part of the checklist confirmed.') }}</p>
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
                                — {{ $part['state'] === 'rejected' ? __('rejected') : __('not checked yet') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="text-sm text-ink" data-blocked-hint>
                    {{ count($blockedParts) === 1 ? __('To approve, change it to Confirm.') : __('To approve, change these to Confirm.') }}
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

    @if ($panel === Page::PANEL_EVALUATION)
        {{-- Require Office Visit: what the requester must bring (REQ-20-04). --}}
        <flux:modal name="office-visit" class="w-full md:w-[32rem]" data-office-visit-modal>
            <form wire:submit="requireOfficeVisit" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Require Office Visit') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Tick what the requester must bring or complete at the office.') }}</flux:text>
                </div>
                {{-- The credential's list, then "Other", whose field shows once it is ticked (REQ-20-04). --}}
                <flux:checkbox.group wire:model="requiredItems" :label="__('Requirements')">
                    @foreach ($request->credentialType->requirements ?? [] as $requirement)
                        <flux:checkbox :value="$requirement" :label="$requirement" />
                    @endforeach
                    <flux:checkbox :value="Page::OTHER_ITEM" :label="__('Other')" data-other-checkbox />
                </flux:checkbox.group>
                <div x-show="($wire.requiredItems ?? []).includes(@js(Page::OTHER_ITEM))" @unless (in_array(Page::OTHER_ITEM, $requiredItems, true)) x-cloak style="display: none" @endunless data-other-field>
                    <flux:input wire:model="otherRequirement" :label="__('Please specify')" :placeholder="__('e.g. Completed clearance form')" maxlength="250" />
                </div>
                <flux:textarea wire:model="remarks" :label="__('Remarks')" badge="Optional" rows="3" data-office-visit-remarks />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="building-office">{{ __('Require Office Visit') }}</flux:button>
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
</div>
