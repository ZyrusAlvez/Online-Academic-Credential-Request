<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('department.dashboard')" wire:navigate>{{ __('Office Queue') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$request->student->name">
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

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request details') }}</h2>
                <x-request-details :request="$request" />
            </section>

            <section aria-labelledby="visits-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="visits-heading" class="text-lg font-semibold text-primary">{{ __('Office visits') }}</h2>
                <x-office-visits :visits="$request->visits" />
            </section>

            <section aria-labelledby="history-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="history-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('History') }}</h2>
                <x-request-timeline :events="$request->events" />
            </section>
        </div>

        <aside class="space-y-6">
            @php($visit = $request->openVisit)
            @if ($visit)
                @can('act', $visit)
                    <section aria-labelledby="action-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5">
                        <div>
                            <h2 id="action-heading" class="text-lg font-semibold text-primary">{{ __('Your office') }}</h2>
                            <p class="text-xs text-zinc-600">{{ $visit->status->label() }}</p>
                        </div>
                        @include('partials.visit-actions', ['visit' => $visit, 'returnOffices' => $returnOffices])
                        @include('partials.delay-modal')
                    </section>
                @else
                    <flux:callout icon="information-circle" color="sky">
                        <flux:callout.text>{{ __('This document is currently with :office.', ['office' => $visit->department->name]) }}</flux:callout.text>
                    </flux:callout>
                @endcan
            @else
                <flux:callout icon="information-circle" color="sky">
                    <flux:callout.text>{{ __('No action needed from your office. Status: :status.', ['status' => $request->status->label()]) }}</flux:callout.text>
                </flux:callout>
            @endif

            <section aria-labelledby="route-heading" class="rounded-xl border border-secondary/50 p-5">
                <h2 id="route-heading" class="mb-4 font-semibold text-primary">{{ __('Route') }}</h2>
                <x-request-route :steps="$request->steps" />
            </section>
        </aside>
    </div>
</div>
