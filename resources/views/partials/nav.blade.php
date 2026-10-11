@php($user = auth()->user())

<flux:navlist variant="outline">
    <flux:navlist.group :heading="$user->role->label()" class="grid">
        @switch($user->role)
            @case(\App\Enums\Role::Student)
                <flux:navlist.item icon="document-text" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" wire:navigate>{{ __('My Requests') }}</flux:navlist.item>
                @break

            @case(\App\Enums\Role::Registrar)
                <flux:navlist.item icon="home" :href="route('registrar.dashboard')" :current="request()->routeIs('registrar.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                @break

            @case(\App\Enums\Role::Department)
                <flux:navlist.item icon="inbox-stack" :href="route('department.dashboard')" :current="request()->routeIs('department.dashboard')" wire:navigate>{{ __('Signature Queue') }}</flux:navlist.item>
                @break
        @endswitch
    </flux:navlist.group>
</flux:navlist>
