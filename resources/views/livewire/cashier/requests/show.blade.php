<div class="mx-auto max-w-4xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('cashier.payments')" wire:navigate>{{ __('Payment Validation') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$request->requester_name">
        <x-slot:afterTitle>
            <x-copy-button :value="$request->reference_no" :label="__('Copy tracking number')" :copied="__('Tracking number copied.')" />
        </x-slot:afterTitle>
        <x-slot:actions>
            <x-request-status :status="$request->status" class="self-center" />
        </x-slot:actions>
    </x-page-header>

    {{-- The proof and its details, read-only (REQ-16-17, REQ-25-05). --}}
    @include('partials.payment-review')

    {{-- The credentials the payment is for (REQ-27-09). --}}
    @if ($credentials->count() > 1)
        <x-application-credentials :requests="$credentials" :total="$request->amount_due" />
    @endif

    <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request Details') }}</h2>
        <x-request-details :request="$request">
            <x-summary-row :label="__('Email and contact number')" class="[&_dd]:break-all">{{ $request->requesterEmail() ?? '—' }}@if ($request->contact_number) · {{ $request->contact_number }}@endif</x-summary-row>
        </x-request-details>
    </section>

    {{-- The decision, last on the page (REQ-16-27). --}}
    <section aria-labelledby="decision-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6" data-decision>
        <h2 id="decision-heading" class="text-lg font-semibold text-primary">{{ __('Decision') }}</h2>

        <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="3" />

        <div class="flex flex-wrap justify-center gap-2" data-decision-buttons>
            <flux:button wire:click="decline" variant="danger" icon="no-symbol" data-decline-button data-confirm-title="{{ __('Decline request') }}" data-confirm-label="{{ __('Decline') }}" :wire:confirm="__('Decline :ref? The requester is notified with your remarks. This cannot be undone.', ['ref' => $request->reference_no])">{{ __('Decline') }}</flux:button>
            <flux:button wire:click="returnRequest" icon="arrow-uturn-left" data-return-button>{{ __('Return to Requester') }}</flux:button>
            <flux:button wire:click="approve" variant="primary" icon="check" data-approve-button>{{ __('Approve') }}</flux:button>
        </div>
    </section>
</div>
