<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Email notifications')" :subheading="$requester ? __('Choose whether we email you about your requests') : __('Choose whether we email you about requests')">
        <div class="my-6 space-y-4">
            <flux:switch
                wire:model.live="enabled"
                :label="$requester ? __('Email me about my requests') : __('Email me about requests')"
                :description="__('Turned off, you still see every update in the app.')"
            />

            @if ($requester)
                {{-- REQ-06-16: the one kind of email the switch does not stop (user's request, 2026-11-12). --}}
                <flux:callout icon="exclamation-triangle" color="amber" data-action-required-note>
                    <flux:callout.text>{{ __('Notifications marked [ACTION REQUIRED] are always emailed, even when this is turned off.') }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </x-settings.layout>
</section>
