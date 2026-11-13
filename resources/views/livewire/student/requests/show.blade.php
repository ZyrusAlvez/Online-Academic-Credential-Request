<div class="mx-auto max-w-4xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('Request Tracker') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->credentialType->name">
        <x-slot:description>
            <span class="inline-flex items-center gap-1">
                {{ __('Tracking no. :ref', ['ref' => $request->reference_no]) }}
                <x-copy-button :value="$request->reference_no" :label="__('Copy tracking number')" :copied="__('Tracking number copied.')" />
            </span>
        </x-slot:description>
        <x-slot:actions>
            @can('cancel', $request)
                <flux:modal.trigger name="cancel-request">
                    <flux:button variant="ghost" icon="x-circle">{{ __('Cancel request') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section aria-labelledby="whereabouts-heading" class="rounded-xl bg-primary p-5 text-white sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="whereabouts-heading" class="text-sm font-medium tracking-wide text-secondary uppercase">{{ __('Where Is My Request?') }}</h2>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-primary">{{ $request->status->customerLabel() }}</span>
        </div>
        <p class="mt-2 text-2xl font-bold">{{ $request->whereabouts() }}</p>
        @if ($request->status === \App\Enums\RequestStatus::Declined)
            <p class="mt-2 rounded-md bg-white/10 p-3 text-sm"><span class="font-semibold">{{ __('Reason:') }}</span> {{ $request->decline_reason }}</p>
        @elseif ($request->status === \App\Enums\RequestStatus::Cancelled && $request->cancel_reason)
            <p class="mt-2 text-sm text-secondary">{{ $request->cancel_reason }}</p>
        @endif
    </section>

    @if ($request->status === \App\Enums\RequestStatus::ReturnedForClarification)
        <section aria-labelledby="clarification-heading" class="space-y-4 rounded-xl border border-amber-500 bg-amber-50 p-5 sm:p-6" data-clarification-panel>
            {{-- Returned for clarification: what was rejected, why, and Edit Request (REQ-20-05). --}}
            <h2 id="clarification-heading" class="text-lg font-semibold text-amber-900">{{ __('Please Correct Your Request') }}</h2>
            @if ($rejectedParts !== [])
                <div>
                    <p class="text-sm text-amber-950">{{ __('The Office of the Registrar could not confirm:') }}</p>
                    <ul class="mt-1 space-y-1" data-rejected-parts>
                        @foreach ($rejectedParts as $part)
                            <li class="flex items-start gap-2 text-sm">
                                <flux:icon.exclamation-circle variant="mini" class="mt-0.5 shrink-0 text-amber-700" />
                                <span class="font-medium text-ink">{{ __($part->label()) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($returnRemarks !== '')
                <p class="rounded-md bg-white p-3 text-sm whitespace-pre-line text-ink"><span class="font-semibold">{{ __('Remarks:') }}</span> {{ $returnRemarks }}</p>
            @endif
            @can('edit', $request)
                <flux:button :href="route('student.requests.edit', $request)" wire:navigate variant="primary" icon="pencil-square" data-edit-request>{{ __('Edit Request') }}</flux:button>
            @endcan
        </section>
    @endif

    @if ($request->status === \App\Enums\RequestStatus::PendingCompliance)
        <section aria-labelledby="compliance-heading" class="space-y-4 rounded-xl border border-amber-500 bg-amber-50 p-5 sm:p-6" data-office-visit-panel>
            {{-- Office visit: what to bring; nothing to do online (REQ-20-08). --}}
            <h2 id="compliance-heading" class="text-lg font-semibold text-amber-900">{{ __('Visit the Office of the Registrar') }}</h2>
            <div>
                <p class="text-sm text-amber-950">{{ __('Bring the originals of, or complete:') }}</p>
                <ul class="mt-1 space-y-1" data-required-items>
                    @foreach ($request->deficiencies as $deficiency)
                        <li class="flex items-start gap-2 text-sm">
                            @if ($deficiency->resolved_at)
                                <flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-green-700" />
                                <span class="text-ink"><span class="font-medium">{{ $deficiency->description }}</span> · {{ __('received :date', ['date' => $deficiency->resolved_at->format('M j, Y')]) }}</span>
                            @else
                                <flux:icon.exclamation-circle variant="mini" class="mt-0.5 shrink-0 text-amber-700" />
                                <span class="font-medium text-ink">{{ $deficiency->description }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            @if ($returnRemarks !== '')
                <p class="rounded-md bg-white p-3 text-sm whitespace-pre-line text-ink"><span class="font-semibold">{{ __('Remarks:') }}</span> {{ $returnRemarks }}</p>
            @endif
            {{-- A representative only when one was declared on New Request (REQ-11-05). --}}
            <p class="text-sm text-amber-950" data-office-visit-who>
                @if ($representative)
                    {{ __('Come in person, or send :name, your representative, with the Special Power of Attorney (SPA) and the valid IDs of both of you.', ['name' => $representative->full_name]) }}
                @else
                    {{ __('Come in person with a valid ID.') }}
                @endif
            </p>
        </section>
    @endif

    @if ($request->requires_payment && in_array($request->status, [\App\Enums\RequestStatus::AwaitingPayment, \App\Enums\RequestStatus::PaymentVerification], true))
        <section aria-labelledby="payment-heading" class="space-y-5 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="payment-heading" class="text-lg font-semibold text-primary">{{ __('Payment Instructions') }}</h2>
                @if ($request->amount_due !== null)
                    <span class="text-lg font-bold text-ink">{{ __('Amount due: ₱:amount', ['amount' => number_format((float) $request->amount_due, 2)]) }}</span>
                @endif
            </div>

            @if ($request->status === \App\Enums\RequestStatus::AwaitingPayment)
                @if ($rejectedProof)
                    <flux:callout icon="exclamation-triangle" color="red">
                        <flux:callout.heading>{{ __('Your last proof of payment was not accepted') }}</flux:callout.heading>
                        <flux:callout.text>{{ $rejectedProof->remarks }}</flux:callout.text>
                    </flux:callout>
                @endif

                <ol class="space-y-5">
                    <li class="flex gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white" aria-hidden="true">1</span>
                        <div class="space-y-2">
                            <p class="text-sm">{{ __('Pay on the UPHSL payment portal and keep your receipt.') }}</p>
                            <flux:button :href="config('uphsl.payment_url')" target="_blank" rel="noopener" variant="primary" icon-trailing="arrow-top-right-on-square">
                                {{ __('Pay online') }}<span class="sr-only"> ({{ __('opens in a new tab') }})</span>
                            </flux:button>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white" aria-hidden="true">2</span>
                        <form wire:submit="uploadReceipt" class="w-full space-y-4">
                            <p class="text-sm">{{ __('Upload your proof of payment (JPG, PNG, WebP or PDF, up to 5 MB).') }}</p>
                            <flux:input type="file" wire:model="receipt" :label="__('Proof of payment')" accept=".jpg,.jpeg,.png,.webp,.pdf" required />
                            <div wire:loading wire:target="receipt" class="text-sm text-zinc-600">{{ __('Uploading…') }}</div>
                            <div class="grid gap-4 md:grid-cols-3">
                                <flux:input wire:model="paymentReference" :label="__('Payment reference no.')" required />
                                <flux:input wire:model="amountPaid" type="number" step="0.01" min="0" inputmode="decimal" :label="__('Amount paid (₱)')" required />
                                <flux:input wire:model="paidOn" type="date" :max="now()->format('Y-m-d')" :label="__('Date paid')" required />
                            </div>
                            <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="receipt,uploadReceipt">{{ __('Submit proof of payment') }}</flux:button>
                        </form>
                    </li>
                </ol>
            @else
                <flux:callout icon="clock" color="sky">
                    <flux:callout.text>{{ __('Your payment is being verified.') }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($request->paymentProofs->isNotEmpty())
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Uploaded Proofs of Payment') }}</h3>
                    <x-payment-proof-list :proofs="$request->paymentProofs" />
                </div>
            @endif
        </section>
    @endif

    @if ($request->status === \App\Enums\RequestStatus::ReadyForRelease)
        <section aria-labelledby="claim-heading" class="space-y-3 rounded-xl border border-green-700 bg-green-50 p-5 sm:p-6">
            <h2 id="claim-heading" class="text-lg font-semibold text-green-900">{{ __('Your Credential Is Ready for Release') }}</h2>
            <p class="text-sm text-green-950" data-claim-who>
                @if ($representative)
                    {{ __('Claim it at the Office of the Registrar with a valid ID, or send :name, your representative, with the SPA and the valid IDs of both of you.', ['name' => $representative->full_name]) }}
                @else
                    {{ __('Claim it at the Office of the Registrar with a valid ID.') }}
                @endif
            </p>
        </section>
    @endif

    <section aria-labelledby="tracker-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="tracker-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('Progress') }}</h2>
        <x-request-tracker :request="$request" :events="$events" />
    </section>

    {{-- Only when one was named on New Request; none is added afterwards (REQ-11-01). --}}
    @if ($request->representatives->isNotEmpty())
        <section aria-labelledby="representatives-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="representatives-heading" class="text-lg font-semibold text-primary">{{ __('Authorized Representative') }}</h2>
            <x-representative-list :representatives="$request->representatives" hide-pending />
        </section>
    @endif

    <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request Details') }}</h2>
        {{-- Field left, value right; the ID and the requirement files are rows too (REQ-09-22). --}}
        <x-request-details :request="$request">
            @if ($request->identityDocument)
                {{-- The ID sent with this request, without a verification badge: "Verified" shows on
                     New Request and Settings → Profile only (REQ-15-08, REQ-21-06). --}}
                <x-summary-row :label="__('Valid ID')" data-request-id>
                    <x-file-link :href="route('identity-documents.show', $request->identityDocument)" :name="$request->identityDocument->id_type.': '.$request->identityDocument->original_name" :type="$request->identityDocument->mime_type" class="break-all">
                        {{ $request->identityDocument->id_type }}: {{ $request->identityDocument->original_name }}
                    </x-file-link>
                </x-summary-row>
            @endif
            {{-- The copies of the requirements sent with this request (REQ-04-19). --}}
            <x-requirement-document-list :request="$request" />
        </x-request-details>
    </section>

    {{-- The full history comes last (REQ-09-22). --}}
    <section aria-labelledby="history-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="history-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('Tracking History') }}</h2>
        <x-request-timeline :events="$events" />
    </section>

    @can('cancel', $request)
        <flux:modal name="cancel-request" class="w-full md:w-[28rem]">
            <form wire:submit="cancel" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Cancel this request?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('This cannot be undone. You can submit a new request later.') }}</flux:text>
                </div>
                <flux:textarea wire:model="cancelReason" :label="__('Reason')" badge="Optional" rows="2" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Keep request') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Cancel request') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
