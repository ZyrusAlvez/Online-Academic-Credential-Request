<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('registrar.requests')" wire:navigate>{{ __('Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$request->student->name">
        <x-slot:actions>
            <x-request-status :status="$request->status" class="self-center" />
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
                <p class="mt-4 text-sm text-zinc-600">
                    {{ __('Requested by') }} <span class="font-medium text-ink">{{ $request->student->name }}</span>
                    &lt;<a href="mailto:{{ $request->student->email }}" class="text-highlight underline underline-offset-2">{{ $request->student->email }}</a>&gt;
                </p>
            </section>

            @if ($request->requires_payment)
                <section aria-labelledby="proofs-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="proofs-heading" class="text-lg font-semibold text-primary">{{ __('Proof of payment') }}</h2>

                    @if ($pendingProof && $pendingProof->isImage())
                        <a href="{{ route('payment-proofs.show', $pendingProof) }}" target="_blank" rel="noopener" class="block">
                            <img src="{{ route('payment-proofs.show', $pendingProof) }}" alt="{{ __('Receipt uploaded by the student') }}" class="max-h-96 w-auto rounded-lg border border-secondary/50" loading="lazy">
                        </a>
                    @endif

                    @if ($request->paymentProofs->isEmpty())
                        <p class="text-sm text-zinc-600">{{ __('The student has not uploaded a receipt yet.') }}</p>
                    @else
                        <x-payment-proof-list :proofs="$request->paymentProofs" />
                    @endif
                </section>
            @endif

            @if ($request->steps->isNotEmpty())
                <section aria-labelledby="route-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                    <h2 id="route-heading" class="text-lg font-semibold text-primary">{{ __('Signing route') }}</h2>
                    <ol class="mt-4 space-y-2">
                        @foreach ($request->steps as $step)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span><span class="text-zinc-500">{{ $step->position }}.</span> {{ $step->department->name }}</span>
                                <flux:badge size="sm" :color="$step->status->color()">{{ $step->status->label() }}</flux:badge>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @if ($request->status->needsRegistrarReview())
                <section aria-labelledby="review-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5">
                    <h2 id="review-heading" class="text-lg font-semibold text-primary">{{ __('Review') }}</h2>
                    <p class="text-sm text-zinc-700">
                        @if ($request->status === \App\Enums\RequestStatus::PaymentReview)
                            {{ __('Check the receipt against the payment portal. Accepting it routes the request to the first office.') }}
                        @else
                            {{ __('Check the details. Approving routes the request to the first office.') }}
                        @endif
                    </p>

                    <form wire:submit="approve" class="space-y-3">
                        <flux:textarea wire:model="remarks" :label="__('Remarks')" badge="Optional" rows="2" />
                        <flux:button type="submit" variant="primary" icon="check" class="w-full">
                            {{ $request->status === \App\Enums\RequestStatus::PaymentReview ? __('Accept payment & route') : __('Approve & route') }}
                        </flux:button>
                    </form>

                    @if ($request->status === \App\Enums\RequestStatus::PaymentReview)
                        <flux:modal.trigger name="reject-payment">
                            <flux:button variant="danger" icon="x-mark" class="w-full">{{ __('Reject payment') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                </section>
            @endif

            @if ($request->status->isBeforeRouting())
                <section aria-labelledby="college-heading" class="rounded-xl border border-secondary/50 p-5">
                    <h2 id="college-heading" class="font-semibold text-primary">{{ __('College') }}</h2>
                    <form wire:submit="changeCollege" class="mt-3 space-y-3">
                        <flux:select wire:model="collegeId" :label="__('Signing college')" :description="__('Correct this if the student chose the wrong college.')">
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

    <flux:modal name="reject-payment" class="w-full md:w-[28rem]">
        <form wire:submit="rejectPayment" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reject payment') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The student will see your remarks and can upload a new receipt.') }}</flux:text>
            </div>
            <flux:textarea wire:model="rejectRemarks" :label="__('Remarks')" :placeholder="__('e.g. Amount does not match the fee')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Reject payment') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="cancel-request" class="w-full md:w-[28rem]">
        <form wire:submit="cancel" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel this request?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The student will be notified with your reason. This cannot be undone.') }}</flux:text>
            </div>
            <flux:textarea wire:model="cancelReason" :label="__('Reason')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Cancel request') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
