<div class="mx-auto max-w-4xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('My Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->credentialType->name">
        <x-slot:description>
            <span class="inline-flex items-center gap-1">
                {{ __('Reference no. :ref', ['ref' => $request->reference_no]) }}
                <x-copy-button :value="$request->reference_no" :label="__('Copy reference number')" :copied="__('Reference number copied.')" />
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
            <h2 id="whereabouts-heading" class="text-sm font-medium tracking-wide text-secondary uppercase">{{ __('Where is my document?') }}</h2>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-primary">{{ $request->status->label() }}</span>
        </div>
        <p class="mt-2 text-2xl font-bold">{{ $request->whereabouts() }}</p>
        @if ($request->currentStep?->status === \App\Enums\StepStatus::OnHold)
            <p class="mt-2 rounded-md bg-white/10 p-3 text-sm">
                <span class="font-semibold">{{ __('On hold:') }}</span> {{ $request->currentStep->remarks }}
            </p>
        @elseif ($request->status === \App\Enums\RequestStatus::Cancelled && $request->cancel_reason)
            <p class="mt-2 text-sm text-secondary">{{ $request->cancel_reason }}</p>
        @endif
    </section>

    @if ($request->requires_payment && $request->status->isBeforeRouting())
        <section aria-labelledby="payment-heading" class="space-y-5 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="payment-heading" class="text-lg font-semibold text-primary">{{ __('Payment') }}</h2>
                @if ($request->fee !== null)
                    <span class="text-lg font-bold text-ink">₱{{ number_format((float) $request->fee, 2) }}</span>
                @endif
            </div>

            @if ($request->status === \App\Enums\RequestStatus::AwaitingPayment)
                @if ($rejectedProof)
                    <flux:callout icon="exclamation-triangle" color="red">
                        <flux:callout.heading>{{ __('Your last receipt was not accepted') }}</flux:callout.heading>
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
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:input wire:model="paymentReference" :label="__('Payment reference no.')" badge="Optional" />
                                <flux:input wire:model="amountPaid" type="number" step="0.01" min="0" inputmode="decimal" :label="__('Amount paid (₱)')" badge="Optional" />
                            </div>
                            <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="receipt,uploadReceipt">{{ __('Submit receipt') }}</flux:button>
                        </form>
                    </li>
                </ol>
            @else
                <flux:callout icon="clock" color="sky">
                    <flux:callout.text>{{ __('Your receipt is being verified by the Office of the Registrar.') }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($request->paymentProofs->isNotEmpty())
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Uploaded receipts') }}</h3>
                    <x-payment-proof-list :proofs="$request->paymentProofs" />
                </div>
            @endif
        </section>
    @endif

    @if ($request->steps->isNotEmpty())
        <section aria-labelledby="route-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="route-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Signing route') }}</h2>
            <x-signing-route :steps="$request->steps" />
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
