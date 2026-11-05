<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Email notifications')" :subheading="__('Choose whether we email you about your requests')">
        <div class="my-6 space-y-4">
            <flux:switch
                wire:model.live="enabled"
                :label="__('Email me about my requests')"
                :description="__('Updates on your requests, delay notices and the result of your valid ID check. Turned off, you still see every update under the bell in the app.')"
            />

            <flux:text class="text-sm">
                {{ __('Verification codes and password-reset emails are always sent.') }}
            </flux:text>
        </div>
    </x-settings.layout>
</section>
