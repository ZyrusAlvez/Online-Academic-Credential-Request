<div class="mx-auto max-w-4xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('My Requests') }}</flux:breadcrumbs.item>
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
            <h2 id="whereabouts-heading" class="text-sm font-medium tracking-wide text-secondary uppercase">{{ __('Where is my request?') }}</h2>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-primary">{{ $request->status->customerLabel() }}</span>
        </div>
        <p class="mt-2 text-2xl font-bold">{{ $request->whereabouts() }}</p>
        @if ($request->status === \App\Enums\RequestStatus::Declined)
            <p class="mt-2 rounded-md bg-white/10 p-3 text-sm"><span class="font-semibold">{{ __('Reason:') }}</span> {{ $request->decline_reason }}</p>
        @elseif ($request->status === \App\Enums\RequestStatus::Cancelled && $request->cancel_reason)
            <p class="mt-2 text-sm text-secondary">{{ $request->cancel_reason }}</p>
        @endif
    </section>

    @if ($request->status === \App\Enums\RequestStatus::PendingCompliance)
        <section aria-labelledby="compliance-heading" class="space-y-4 rounded-xl border border-amber-500 bg-amber-50 p-5 sm:p-6">
            <h2 id="compliance-heading" class="text-lg font-semibold text-amber-900">{{ __('Important notice: incomplete requirements') }}</h2>
            <p class="text-sm text-amber-950">{{ __('The Office of the Registrar found the following requirements incomplete. Submit the original documents and accomplish the clearance at the Registrar\'s Office of UPHSL, personally or through an authorized representative with a Special Power of Attorney (SPA) and the valid IDs of both of you.') }}</p>
            <ul class="space-y-2">
                @foreach ($request->deficiencies as $deficiency)
                    <li class="flex items-start gap-2 text-sm">
                        @if ($deficiency->resolved_at)
                            <flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-green-700" />
                            <span><span class="line-through">{{ $deficiency->description }}</span> <span class="text-green-800">{{ __('— complied :date', ['date' => $deficiency->resolved_at->format('M j')]) }}</span></span>
                        @else
                            <flux:icon.exclamation-circle variant="mini" class="mt-0.5 shrink-0 text-amber-700" />
                            <span class="font-medium text-ink">{{ $deficiency->description }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-amber-950">{{ __('Your request remains pending until these are resolved and verified. The processing period starts only after your requirements and payment are verified and the request is accepted.') }}</p>
        </section>
    @endif

    @if ($request->status === \App\Enums\RequestStatus::ReturnedForCustomerAction)
        <section aria-labelledby="action-heading" class="space-y-4 rounded-xl border border-amber-500 bg-amber-50 p-5 sm:p-6">
            <h2 id="action-heading" class="text-lg font-semibold text-amber-900">{{ __('Your action is needed') }}</h2>
            <p class="rounded-md bg-white p-3 text-sm text-ink">{{ $actionMessage }}</p>
            @can('respond', $request)
                <form wire:submit="respond" class="space-y-3">
                    <flux:textarea wire:model="reply" :label="__('Your reply')" :description="__('If you need to submit something in person, say when you will come.')" rows="3" required />
                    <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send reply') }}</flux:button>
                </form>
            @endcan
        </section>
    @endif

    @if ($request->requires_payment && in_array($request->status, [\App\Enums\RequestStatus::AwaitingPayment, \App\Enums\RequestStatus::PaymentVerification], true))
        <section aria-labelledby="payment-heading" class="space-y-5 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="payment-heading" class="text-lg font-semibold text-primary">{{ __('Payment instructions') }}</h2>
                @if ($request->amount_due !== null)
                    <span class="text-lg font-bold text-ink">{{ __('Amount due: ₱:amount', ['amount' => number_format((float) $request->amount_due, 2)]) }}</span>
                @endif
            </div>

            @if ($request->status === \App\Enums\RequestStatus::AwaitingPayment)
                <p class="text-sm text-zinc-700">{{ __('Your requirements are complete. Pay the amount due, then upload your proof of payment with the transaction details.') }}</p>
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
                            <p class="text-sm">{{ __('Pay on the UPHSL online payment portal. Keep your receipt or take a screenshot of the confirmation.') }}</p>
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
                            <div class="grid gap-4 sm:grid-cols-3">
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
                    <flux:callout.text>{{ __('Your proof of payment is being verified against the official payment record. An uploaded screenshot alone does not mark a request as paid.') }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($request->paymentProofs->isNotEmpty())
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Uploaded proofs of payment') }}</h3>
                    <x-payment-proof-list :proofs="$request->paymentProofs" />
                </div>
            @endif
        </section>
    @endif

    @if ($request->status === \App\Enums\RequestStatus::ReadyForRelease)
        <section aria-labelledby="claim-heading" class="space-y-3 rounded-xl border border-green-700 bg-green-50 p-5 sm:p-6">
            <h2 id="claim-heading" class="text-lg font-semibold text-green-900">{{ __('Your credential is ready for release') }}</h2>
            <p class="text-sm text-green-950">{{ __('Claim it at the Office of the Registrar during office hours. Bring a valid ID. If someone claims it for you, they must present a Special Power of Attorney (SPA) and the valid IDs of both of you; the credential is released only after their authority is verified.') }}</p>
        </section>
    @endif

    <section aria-labelledby="tracker-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="tracker-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('Progress') }}</h2>
        <x-request-tracker :request="$request" />
    </section>

    @if ($request->representatives->isNotEmpty() || auth()->user()->can('declareRepresentative', $request))
        <section aria-labelledby="representatives-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="representatives-heading" class="text-lg font-semibold text-primary">{{ __('Authorized representative') }}</h2>
                @can('declareRepresentative', $request)
                    <flux:modal.trigger name="declare-representative">
                        <flux:button size="sm" icon="user-plus">{{ __('Add representative') }}</flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>
            @if ($request->representatives->isEmpty())
                <p class="text-sm text-zinc-600">{{ __('You are transacting personally. Add a representative if someone will submit documents or claim the credential for you.') }}</p>
            @else
                <x-representative-list :representatives="$request->representatives" />
            @endif
        </section>
    @endif

    <section aria-labelledby="history-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="history-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('Tracking history') }}</h2>
        <x-request-timeline :events="$events" />
    </section>

    <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request details') }}</h2>
        <x-request-details :request="$request" />
    </section>

    @can('declareRepresentative', $request)
        <flux:modal name="declare-representative" class="w-full md:w-[32rem]">
            <form wire:submit="declareRepresentative" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Add an authorized representative') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('They must present a Special Power of Attorney (SPA) and the valid IDs of both of you. Uploading them now is optional and lets the Registrar check them ahead.') }}</flux:text>
                </div>
                <flux:input wire:model="representativeName" :label="__('Full name')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="representativeRelationship" :label="__('Relationship to you')" badge="Optional" />
                    <flux:input wire:model="representativeContact" type="tel" :label="__('Contact number')" badge="Optional" />
                </div>
                <flux:input type="file" wire:model="spa" :label="__('SPA')" badge="Optional" accept=".jpg,.jpeg,.png,.webp,.pdf" />
                <flux:input type="file" wire:model="representativeId" :label="__('Representative\'s valid ID')" badge="Optional" accept=".jpg,.jpeg,.png,.webp,.pdf" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="spa,representativeId,declareRepresentative">{{ __('Add representative') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

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
