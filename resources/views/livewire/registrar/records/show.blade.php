{{-- An application's record; a tab per credential when it has several (REQ-27-08). $request is the
     credential shown. --}}
@php($several = $credentials->count() > 1)
@php($trackingNo = $several ? $request->application->reference_no : $request->reference_no)
<div class="mx-auto max-w-4xl space-y-6" data-read-only-record>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('records')" wire:navigate>{{ __('Records') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $trackingNo }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$trackingNo" :description="($several ? trans_choice(':count credential|:count credentials', $credentials->count()) : $request->credentialType->name).' · '.$request->requester_name">
        <x-slot:afterTitle>
            <x-copy-button :value="$trackingNo" :label="__('Copy tracking number')" :copied="__('Tracking number copied.')" />
        </x-slot:afterTitle>
        <x-slot:actions>
            <x-walk-in-badge :request="$request" class="self-center" />
            @unless ($several)
                <x-request-status :status="$request->status" class="self-center" />
                @if ($request->isOverdue())
                    <flux:badge color="red" icon="clock" class="self-center">{{ __('Overdue since :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
                @endif
            @endunless
        </x-slot:actions>
    </x-page-header>

    <x-credential-tabs :requests="$credentials" :current="$request" />

    @if ($several)
        <div class="flex flex-wrap items-center gap-2" data-credential-status>
            <x-request-status :status="$request->status" />
            @if ($request->isOverdue())
                <flux:badge color="red" icon="clock">{{ __('Overdue since :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
            @endif
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-zinc-600">{{ __('Where it is now: :where', ['where' => $request->staffWhereabouts()]) }}</p>
        {{-- The Admin records a stuck credential's next step (REQ-35-01). --}}
        @can('override', $request)
            <flux:button size="sm" icon="bolt" wire:click="openOverride" data-override-button>{{ __('Override') }}</flux:button>
        @endcan
    </div>

    @include('partials.request-record', ['hide' => [], 'events' => $events])

    @can('override', $request)
        @include('partials.override-dialog')
    @endcan
</div>
