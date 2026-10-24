@props(['representatives', 'reviewable' => false])

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
                <flux:badge size="sm" :color="$representative->status->color()">{{ $representative->status->label() }}</flux:badge>
            </div>
            @if ($representative->documents->isNotEmpty())
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    @foreach ($representative->documents as $document)
                        <li>
                            <a href="{{ route('request-documents.show', $document) }}" target="_blank" rel="noopener" class="text-highlight underline underline-offset-2 hover:no-underline">
                                {{ $document->kind->label() }}<span class="sr-only"> ({{ __('opens in a new tab') }})</span>
                            </a>
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
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="primary" icon="check" wire:click="verifyRepresentative({{ $representative->id }})" :wire:confirm="__('Confirm that the SPA and the IDs of the owner and :name were checked.', ['name' => $representative->full_name])">{{ __('Verify') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="confirmRejectRepresentative({{ $representative->id }})">{{ __('Reject') }}</flux:button>
                </div>
            @endif
        </li>
    @endforeach
</ul>
