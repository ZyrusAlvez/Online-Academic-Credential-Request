@php($role = auth()->user()->role)

<flux:navlist variant="outline">
    @if ($role === \App\Enums\Role::Student)
        <flux:navlist.group class="grid">
            <flux:navlist.item icon="plus-circle" :href="route('student.requests.create')" :current="request()->routeIs('student.requests.create')" wire:navigate>{{ __('New Request') }}</flux:navlist.item>
            <flux:navlist.item icon="document-text" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard', 'student.requests.show')" wire:navigate>{{ __('Request Tracker') }}</flux:navlist.item>
        </flux:navlist.group>
        {{-- Help opens into its topics; open on a help page, with that topic current (REQ-22-01).
             The group is indented, so a label too long for it ends in "…" (full on hover) instead of
             running past the sidebar. --}}
        @php($helpTopic = request()->routeIs('student.help') ? request()->route('topic') : null)
        <flux:navlist.group expandable :expanded="$helpTopic !== null" :heading="__('Help')" class="grid" data-help-nav>
            @foreach (\App\Enums\HelpTopic::cases() as $topic)
                <flux:navlist.item :icon="$topic->icon()" :href="route('student.help', $topic->value)" :current="$helpTopic === $topic->value" wire:navigate
                    :title="__($topic->label())" class="min-w-0 [&_[data-content]]:min-w-0 [&_[data-content]]:truncate">{{ __($topic->label()) }}</flux:navlist.item>
            @endforeach
        </flux:navlist.group>
    @elseif ($role === \App\Enums\Role::Registrar)
        {{-- Initial Evaluation to Release, and Records (REQ-18-03). --}}
        <flux:navlist.group :heading="__('Registrar')" class="grid">
            @foreach (\App\Enums\RegistrarQueue::cases() as $queue)
                <flux:navlist.item :icon="$queue->icon()" :href="route($queue->routeName())" :current="request()->routeIs($queue->routeName())" wire:navigate>{{ __($queue->label()) }}</flux:navlist.item>
            @endforeach
            <flux:navlist.item icon="archive-box" :href="route('records')" :current="request()->routeIs('records*')" wire:navigate>{{ __('Records') }}</flux:navlist.item>
        </flux:navlist.group>
    @elseif ($role === \App\Enums\Role::Admin)
        {{-- The dashboard, Records and Reports, and the Administration pages (REQ-18-02). --}}
        <flux:navlist.group :heading="__('Overview')" class="grid">
            <flux:navlist.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
            <flux:navlist.item icon="archive-box" :href="route('records')" :current="request()->routeIs('records*')" wire:navigate>{{ __('Records') }}</flux:navlist.item>
            <flux:navlist.item icon="chart-bar" :href="route('admin.reports')" :current="request()->routeIs('admin.reports*')" wire:navigate>{{ __('Reports') }}</flux:navlist.item>
        </flux:navlist.group>
        <flux:navlist.group :heading="__('Administration')" class="grid">
            <flux:navlist.item icon="document-duplicate" :href="route('admin.credential-types')" :current="request()->routeIs('admin.credential-types*')" wire:navigate>{{ __('Credential Types') }}</flux:navlist.item>
            <flux:navlist.item icon="building-office-2" :href="route('admin.departments')" :current="request()->routeIs('admin.departments')" wire:navigate>{{ __('Departments') }}</flux:navlist.item>
            <flux:navlist.item icon="academic-cap" :href="route('admin.programs')" :current="request()->routeIs('admin.programs')" wire:navigate>{{ __('Programs') }}</flux:navlist.item>
            <flux:navlist.item icon="clipboard-document-list" :href="route('admin.purposes')" :current="request()->routeIs('admin.purposes')" wire:navigate>{{ __('Purposes') }}</flux:navlist.item>
            <flux:navlist.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>{{ __('Accounts') }}</flux:navlist.item>
            <flux:navlist.item icon="calendar-days" :href="route('admin.calendar')" :current="request()->routeIs('admin.calendar')" wire:navigate>{{ __('Working Calendar') }}</flux:navlist.item>
            <flux:navlist.item icon="queue-list" :href="route('admin.logs')" :current="request()->routeIs('admin.logs')" wire:navigate>{{ __('Logs') }}</flux:navlist.item>
        </flux:navlist.group>
    @else
        <flux:navlist.group :heading="auth()->user()->department?->code ?? __('Department')" class="grid">
            <flux:navlist.item icon="inbox-stack" :href="route('department.dashboard')" :current="request()->routeIs('department.*')" wire:navigate>{{ __('Office Queue') }}</flux:navlist.item>
        </flux:navlist.group>
    @endif
</flux:navlist>
