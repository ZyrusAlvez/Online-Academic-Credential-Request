@php($role = auth()->user()->role)

<flux:navlist variant="outline">
    @if ($role === \App\Enums\Role::Student)
        <flux:navlist.group :heading="__('Student')" class="grid">
            <flux:navlist.item icon="document-text" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard', 'student.requests.show')" wire:navigate>{{ __('My Requests') }}</flux:navlist.item>
            <flux:navlist.item icon="plus-circle" :href="route('student.requests.create')" :current="request()->routeIs('student.requests.create')" wire:navigate>{{ __('New Request') }}</flux:navlist.item>
        </flux:navlist.group>
    @elseif ($role === \App\Enums\Role::Registrar)
        <flux:navlist.group :heading="__('Registrar')" class="grid">
            <flux:navlist.item icon="home" :href="route('registrar.dashboard')" :current="request()->routeIs('registrar.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
            <flux:navlist.item icon="inbox" :href="route('registrar.requests')" :current="request()->routeIs('registrar.requests*')" wire:navigate>{{ __('Requests') }}</flux:navlist.item>
        </flux:navlist.group>
        <flux:navlist.group :heading="__('Administration')" class="grid">
            <flux:navlist.item icon="document-duplicate" :href="route('registrar.credential-types')" :current="request()->routeIs('registrar.credential-types*')" wire:navigate>{{ __('Credential Types') }}</flux:navlist.item>
            <flux:navlist.item icon="building-office-2" :href="route('registrar.departments')" :current="request()->routeIs('registrar.departments')" wire:navigate>{{ __('Departments') }}</flux:navlist.item>
            <flux:navlist.item icon="users" :href="route('registrar.users')" :current="request()->routeIs('registrar.users')" wire:navigate>{{ __('Accounts') }}</flux:navlist.item>
        </flux:navlist.group>
    @else
        <flux:navlist.group :heading="auth()->user()->department?->code ?? __('Department')" class="grid">
            <flux:navlist.item icon="inbox-stack" :href="route('department.dashboard')" :current="request()->routeIs('department.*')" wire:navigate>{{ __('Signature Queue') }}</flux:navlist.item>
        </flux:navlist.group>
    @endif
</flux:navlist>
