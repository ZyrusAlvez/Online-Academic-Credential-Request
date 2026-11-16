<div class="mx-auto max-w-3xl space-y-6">
    @unless ($reviewing)
        <x-page-header :title="__('Walk-in Request')" />
    @endunless

    {{-- Not shown: it announces the step to screen readers and is the focus target of showStep(). --}}
    <p id="request-step" tabindex="-1" class="sr-only">
        {{ $reviewing ? __('Review and submit the walk-in request') : __('Walk-in request') }}
    </p>

    {{-- New Request's sections 1 to 3; no valid ID, no Who Will Transact (REQ-26-02). --}}
    <form wire:submit="review" novalidate @class(['space-y-8', 'hidden' => $reviewing]) data-walk-in-form>
        @include('partials.credential-choice')

        @include('partials.requester-profile-fields', ['prefix' => 'profile', 'fields' => $profile, 'number' => 2, 'withId' => false, 'withEmail' => true, 'idTypes' => [], 'savedId' => null])

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:button type="submit" variant="primary" icon-trailing="arrow-right" wire:loading.attr="disabled" wire:target="review">{{ __('Review request') }}</flux:button>
        </div>
    </form>

    @if ($reviewing)
        <div class="space-y-6">
            <h1 class="text-3xl font-bold text-primary" data-summary-title>{{ __('Summary') }}</h1>

            @foreach ($requestSummary as $heading => $rows)
                <x-summary-section :heading="$heading" :rows="$rows" :level="2" />
            @endforeach

            @include('partials.requester-profile-summary', ['fields' => $profile, 'number' => 2, 'level' => 2, 'withId' => false, 'savedId' => null])

            {{-- In place of the data privacy consent: the evaluator's confirmation (REQ-26-03). --}}
            <section aria-labelledby="validation-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6" data-walk-in-attestation>
                <h2 id="validation-heading" class="text-lg font-semibold text-primary">{{ __('Evaluator\'s Validation') }}</h2>
                <flux:checkbox wire:model="attested" :label="__('I have validated the eligibility of this request and the requester\'s personal and academic information.')" />
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button wire:click="back" wire:loading.attr="disabled" wire:target="back,submit">{{ __('Back to edit') }}</flux:button>
                <flux:button wire:click="submit" variant="primary" icon-trailing="paper-airplane" x-bind:disabled="! $wire.attested" :disabled="! $attested" wire:loading.attr="disabled" wire:target="back,submit" data-submit-request>{{ __('Submit request') }}</flux:button>
            </div>
        </div>
    @endif

    @include('partials.requester-profile-errors')
</div>
