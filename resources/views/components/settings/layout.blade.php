@props(['heading' => '', 'subheading' => '', 'wide' => false])

<div class="flex items-start max-md:flex-col">
    <div class="mr-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            @if (auth()->user()?->hasRole(\App\Enums\Role::Student))
                <flux:navlist.item :href="route('settings.profile')" :current="request()->routeIs('settings.profile')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            @endif
            @if (auth()->user()?->hasRole(\App\Enums\Role::Student, \App\Enums\Role::Registrar, \App\Enums\Role::Department))
                <flux:navlist.item :href="route('settings.notifications')" :current="request()->routeIs('settings.notifications')" wire:navigate>{{ __('Email notifications') }}</flux:navlist.item>
            @endif
            <flux:navlist.item :href="route('settings.password')" :current="request()->routeIs('settings.password')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="min-w-0 flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading }}</flux:heading>
        <flux:subheading>{{ $subheading }}</flux:subheading>

        {{-- The profile's three-column rows need more room than a password form. --}}
        <div @class(['mt-5 w-full', 'max-w-3xl' => $wide, 'max-w-lg' => ! $wide])>
            {{ $slot }}
        </div>
    </div>
</div>
