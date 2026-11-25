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

    {{-- REQ-37-04: who deleted it, when and why; only the Admin gets here. --}}
    @if ($request->trashed())
        <flux:callout icon="trash" color="red" data-deleted-callout>
            <flux:callout.heading>{{ __('Deleted :date by :admin', ['date' => $request->deleted_at->format('M j, Y g:i A'), 'admin' => $request->deleter?->name ?? __('the Admin')]) }}</flux:callout.heading>
            <flux:callout.text>{{ $request->deletion_reason }}</flux:callout.text>
            @can('restore', $request)
                <x-slot name="actions">
                    <flux:button size="sm" icon="arrow-uturn-left" wire:click="restoreRequest" data-restore-button
                        data-confirm-title="{{ __('Restore request') }}" data-confirm-label="{{ __('Restore') }}" wire:confirm="{{ __('Restore :ref? It comes back everywhere exactly as it was.', ['ref' => $trackingNo]) }}">{{ __('Restore') }}</flux:button>
                </x-slot>
            @endcan
        </flux:callout>
    @endif

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
        <div class="flex flex-wrap gap-2">
            {{-- The Admin records a stuck credential's next step (REQ-35-01). --}}
            @can('override', $request)
                <flux:button size="sm" icon="bolt" wire:click="openOverride" data-override-button>{{ __('Override') }}</flux:button>
            @endcan
            {{-- REQ-37-01: the whole application, hidden but kept. --}}
            @can('delete', $request)
                <flux:button size="sm" icon="trash" variant="ghost" class="text-red-700!" wire:click="openDelete" data-delete-button>{{ __('Delete') }}</flux:button>
            @endcan
        </div>
    </div>

    @include('partials.request-record', ['hide' => [], 'events' => $events])

    @can('override', $request)
        @include('partials.override-dialog')
    @endcan

    @can('delete', $request)
        <flux:modal name="delete-request" class="w-full md:w-[30rem]">
            <form wire:submit="deleteRequest" class="space-y-6" data-delete-dialog>
                <flux:heading size="lg">{{ __('Delete :ref', ['ref' => $trackingNo]) }}</flux:heading>
                <flux:text>{{ $several ? __('All :count credentials are hidden from every list, queue and report, and from the requester. You can restore them from Records → Deleted.', ['count' => $credentials->count()]) : __('It is hidden from every list, queue and report, and from the requester. You can restore it from Records → Deleted.') }}</flux:text>
                <flux:textarea wire:model="deletionReason" :label="__('Reason')" rows="3" maxlength="500" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Back') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
