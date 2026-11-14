@props(['representatives', 'reviewable' => false, 'hidePending' => false])

{{-- hide-pending: the requester's page shows no badge until a representative is checked (REQ-11-02). --}}

<ul {{ $attributes->class('divide-y divide-secondary/40 rounded-lg border border-secondary/50') }}>
    @foreach ($representatives as $representative)
        <li class="space-y-2 p-3 text-sm" wire:key="representative-{{ $representative->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-medium text-ink">{{ $representative->full_name }}</p>
                    <p class="text-xs text-zinc-500">
                        {{ $representative->relationship ?? __('Relationship not given') }}
                        @if ($representative->contact_number) · {{ $representative->contact_number }} @endif
                    </p>
                </div>
                @unless ($hidePending && $representative->status === \App\Enums\ReviewStatus::Pending)
                    <flux:badge size="sm" :color="$representative->status->color()">{{ $representative->status->label() }}</flux:badge>
                @endunless
            </div>
            @if ($representative->documents->isNotEmpty())
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    @foreach ($representative->documents as $document)
                        <li>
                            <x-file-link :href="route('request-documents.show', $document)" :name="$document->kind->label().' – '.$representative->full_name" :type="$document->mime_type">
                                {{ $document->kind->label() }}
                            </x-file-link>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-zinc-500">{{ __('No documents uploaded; to be presented in person.') }}</p>
            @endif
            @if ($representative->remarks)
                <p class="text-xs text-zinc-700">{{ __('Remarks: :remarks', ['remarks' => $representative->remarks]) }}</p>
            @endif
            @if ($reviewable && $representative->status === \App\Enums\ReviewStatus::Pending)
                {{-- The approving action on the right (REQ-17-02). --}}
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="confirmRejectRepresentative({{ $representative->id }})">{{ __('Reject') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="check" wire:click="verifyRepresentative({{ $representative->id }})" data-confirm-title="{{ __('Verify representative') }}" data-confirm-label="{{ __('Verify') }}" :wire:confirm="__('Confirm that the SPA and the IDs of the owner and :name were checked.', ['name' => $representative->full_name])">{{ __('Verify') }}</flux:button>
                </div>
            @endif
        </li>
    @endforeach
</ul>
