@props(['user'])

{{-- Requesters must complete their profile before requesting (REQ-01-18). --}}
@unless ($user->canRequestCredentials())
    <flux:callout icon="exclamation-triangle" color="red" {{ $attributes }}>
        <flux:callout.heading>{{ __('Verify your profile before making a request') }}</flux:callout.heading>
        <flux:callout.text>
            <span class="block">{{ __('The Office of the Registrar needs to know who you are before you can request a credential. Still missing from your profile:') }}</span>
            <ul class="mt-2 list-disc ps-5">
                @foreach ($user->missingProfileItems() as $item)
                    <li>{{ __($item) }}</li>
                @endforeach
            </ul>
            @if ($user->identity_status === \App\Enums\IdentityStatus::Rejected && filled($user->latestIdentityDocument?->remarks))
                <span class="mt-2 block">{{ __('Your last ID was not accepted: :reason', ['reason' => $user->latestIdentityDocument->remarks]) }}</span>
            @endif
        </flux:callout.text>
        <x-slot name="actions">
            <flux:button size="sm" variant="primary" :href="route('student.profile')" wire:navigate>{{ __('Complete my profile') }}</flux:button>
        </x-slot>
    </flux:callout>
@endunless
