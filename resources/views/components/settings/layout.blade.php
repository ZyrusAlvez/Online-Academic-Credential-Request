<div class="flex items-start max-md:flex-col">
    <div class="mr-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            @if (auth()->user()?->hasRole(\App\Enums\Role::Student))
                <flux:navlist.item :href="route('settings.notifications')" :current="request()->routeIs('settings.notifications')" wire:navigate>{{ __('Email notifications') }}</flux:navlist.item>
            @endif
            <flux:navlist.item :href="route('settings.password')" :current="request()->routeIs('settings.password')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
