<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Email notifications')" :subheading="__('Choose whether we email you about your requests')">
        <div class="my-6 space-y-4">
            <flux:switch
                wire:model.live="enabled"
                :label="__('Email me about my requests')"
                :description="__('Turned off, you still see every update in the app.')"
            />
        </div>
    </x-settings.layout>
</section>
