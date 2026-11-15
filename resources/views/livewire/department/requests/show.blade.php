@php
    use App\Enums\DepartmentQueue as Q;

    $visit = $request->openVisit;
@endphp

<div class="mx-auto max-w-4xl space-y-6">
    {{-- The list the request is in is the breadcrumb (REQ-24-06). --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route($queue->routeName())" wire:navigate>{{ __($queue->label()) }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$request->requester_name">
        <x-slot:afterTitle>
            <x-copy-button :value="$request->reference_no" :label="__('Copy tracking number')" :copied="__('Tracking number copied.')" />
        </x-slot:afterTitle>
        <x-slot:actions>
            <x-request-status :status="$request->status" class="self-center" />
            @if ($request->isOverdue())
                <flux:badge color="red" icon="clock" class="self-center">{{ __('Overdue since :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Details, the verified proof of payment and the route, then the office's action (REQ-24-07). --}}
    <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request Details') }}</h2>
        <x-request-details :request="$request" />
    </section>

    @if ($proofs->isNotEmpty())
        <section aria-labelledby="proof-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6" data-verified-proof>
            <h2 id="proof-heading" class="text-lg font-semibold text-primary">{{ __('Proof of Payment') }}</h2>
            <x-payment-proof-list :proofs="$proofs" />
        </section>
    @endif

    <section aria-labelledby="route-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="route-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Route') }}</h2>
        <x-request-route :steps="$request->steps" />
    </section>

    {{-- The office's action, laid out like the registrar's Decision panel (REQ-24-07). --}}
    @if ($queue === Q::Incoming && $visit)
        @can('act', $visit)
            <section aria-labelledby="decision-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6" data-office-action>
                <div>
                    <h2 id="decision-heading" class="text-lg font-semibold text-primary">{{ __('Decision') }}</h2>
                    <p class="text-xs text-zinc-600">{{ __('Forwarded :time by :office', ['time' => $visit->forwarded_at->diffForHumans(), 'office' => $visit->holder()->name]) }}</p>
                </div>
                <div class="flex flex-wrap justify-center gap-2">
                    <flux:button wire:click="acceptReceipt" variant="primary" icon="inbox-arrow-down" data-acknowledge>{{ __('Acknowledge receipt') }}</flux:button>
                </div>
            </section>
        @endcan
    @elseif ($queue === Q::ToValidate && $visit)
        {{-- To Forward has no panel: it is forwarded from its list (REQ-24-10). --}}
        @include('partials.visit-actions', ['visit' => $visit, 'returnOffices' => $returnOffices])
    @endif
</div>
