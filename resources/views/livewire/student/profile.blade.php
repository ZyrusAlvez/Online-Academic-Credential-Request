<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Used to fill in your next request.')" wide>
        {{-- Not shown: it announces the step to screen readers and is the focus target of showStep(). --}}
        <p id="profile-step" tabindex="-1" class="sr-only">
            {{ $reviewing ? __('Review and confirm your details') : __('Your details') }}
        </p>

        {{-- Hidden, not removed, on the summary: the file input keeps the chosen ID for "Back to edit".
             novalidate: the browser's popups would stop the submit before the errors modal (REQ-01-26). --}}
        <form wire:submit="review" novalidate @class(['space-y-8', 'hidden' => $reviewing])>
            @include('partials.requester-profile-fields', ['prefix' => 'form', 'fields' => $form, 'number' => null])

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="form.id_file,review">{{ __('Confirm') }}</flux:button>
            </div>
        </form>

        @if ($reviewing)
            <div class="space-y-8">
                <div class="space-y-1">
                    <h2 class="text-xl font-bold text-primary" data-summary-title>{{ __('Summary') }}</h2>
                    <flux:text>{{ __('Check that everything is correct.') }}</flux:text>
                </div>

                @include('partials.requester-profile-summary', ['prefix' => 'form', 'fields' => $form])

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <flux:button wire:click="back" wire:loading.attr="disabled" wire:target="back,save">{{ __('Back to edit') }}</flux:button>
                    <flux:button wire:click="save" variant="primary" x-bind:disabled="! $wire.form.privacy_consent" :disabled="! $form->privacy_consent" wire:loading.attr="disabled" wire:target="back,save" data-save-profile>{{ __('Confirm and save') }}</flux:button>
                </div>
            </div>
        @endif

        @include('partials.requester-profile-errors')
    </x-settings.layout>
</section>
