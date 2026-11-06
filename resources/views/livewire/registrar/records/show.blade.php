<div class="mx-auto max-w-4xl space-y-6" data-read-only-record>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('registrar.records')" wire:navigate>{{ __('Records') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $request->reference_no }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header :title="$request->reference_no" :description="$request->credentialType->name.' · '.$request->requester_name">
        <x-slot:afterTitle>
            <x-copy-button :value="$request->reference_no" :label="__('Copy tracking number')" :copied="__('Tracking number copied.')" />
        </x-slot:afterTitle>
        <x-slot:actions>
            <x-request-status :status="$request->status" class="self-center" />
            @if ($request->isOverdue())
                <flux:badge color="red" icon="clock" class="self-center">{{ __('Overdue since :date', ['date' => $request->effective_due_at->format('M j')]) }}</flux:badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    <p class="text-sm text-zinc-600">{{ __('Where it is now: :where', ['where' => $request->whereabouts()]) }}</p>

    @include('partials.request-record', ['hide' => []])
</div>
