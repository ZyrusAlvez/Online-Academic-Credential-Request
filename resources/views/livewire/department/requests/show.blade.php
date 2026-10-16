<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('department.dashboard')" wire:navigate>{{ __('Signature Queue') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$request->student->name">
        <x-slot:afterTitle>
            <x-copy-button :value="$request->reference_no" :label="__('Copy reference number')" :copied="__('Reference number copied.')" />
        </x-slot:afterTitle>
        <x-slot:actions>
            <x-request-status :status="$request->status" class="self-center" />
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section aria-labelledby="details-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="details-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Request details') }}</h2>
                <x-request-details :request="$request" />
            </section>

            <section aria-labelledby="route-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="route-heading" class="mb-4 text-lg font-semibold text-primary">{{ __('Signing route') }}</h2>
                <x-signing-route :steps="$request->steps" show-actors />
            </section>

            <section aria-labelledby="history-heading" class="rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h2 id="history-heading" class="mb-5 text-lg font-semibold text-primary">{{ __('History') }}</h2>
                <x-request-timeline :events="$request->events" />
            </section>
        </div>

        <aside>
            @php($step = $request->currentStep)
            @if ($step && $request->status === \App\Enums\RequestStatus::InProgress)
                @can('act', $step)
                    <section aria-labelledby="action-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5">
                        <div>
                            <h2 id="action-heading" class="text-lg font-semibold text-primary">{{ __('Your signature') }}</h2>
                            <p class="text-xs text-zinc-600">{{ __('Waiting since :time', ['time' => $step->updated_at->diffForHumans()]) }}</p>
                        </div>
                        @include('partials.step-actions', ['step' => $step])
                    </section>
                @else
                    <flux:callout icon="information-circle" color="sky">
                        <flux:callout.text>{{ __('This request is currently with :office.', ['office' => $step->department->name]) }}</flux:callout.text>
                    </flux:callout>
                @endcan
            @else
                <flux:callout icon="information-circle" color="sky">
                    <flux:callout.text>{{ __('No action needed from your office. Status: :status.', ['status' => $request->status->label()]) }}</flux:callout.text>
                </flux:callout>
            @endif
        </aside>
    </div>
</div>
