{{-- Payment Validation's proof and payment details, read-only (REQ-16-17), on the registrar's and
     the cashier's pages (REQ-25-05). Needs $request, $pendingProof and $paymentNote. --}}

{{-- The requester's note with a new proof, after a return (REQ-04-20). --}}
@if ($paymentNote !== '')
    <p class="rounded-md border border-secondary/50 bg-white p-3 text-sm whitespace-pre-line text-ink" data-payment-note><span class="font-semibold">{{ __('Requester\'s note:') }}</span> {{ $paymentNote }}</p>
@endif
<section aria-labelledby="proof-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6" data-payment-proof>
    <h2 id="proof-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Proof of Payment') }}</h2>
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
</section>
<section aria-labelledby="payment-details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6" data-payment-details>
    <h2 id="payment-details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Payment Details') }}</h2>
    <dl class="divide-y divide-zinc-200 text-sm">
        <x-summary-row :label="__('Amount due')">₱{{ number_format((float) $request->amount_due, 2) }}</x-summary-row>
        <x-summary-row :label="__('Amount paid')">{{ $pendingProof?->amount !== null ? '₱'.number_format((float) $pendingProof->amount, 2) : '—' }}</x-summary-row>
        <x-summary-row :label="__('Reference')" class="[&_dd]:break-all">{{ $pendingProof?->reference_no ?? '—' }}</x-summary-row>
        <x-summary-row :label="__('Date paid')">{{ $pendingProof?->paid_on?->format('M j, Y') ?? '—' }}</x-summary-row>
    </dl>
</section>
