@props(['documents'])

@if ($documents->isNotEmpty())
    <ul {{ $attributes->class('divide-y divide-secondary/40 rounded-lg border border-secondary/50') }}>
        @foreach ($documents as $document)
            <li class="flex flex-wrap items-start justify-between gap-3 p-3 text-sm">
                <div class="min-w-0">
                    <a href="{{ route('identity-documents.show', $document) }}" target="_blank" rel="noopener" class="font-medium break-all text-highlight underline underline-offset-2 hover:no-underline">
                        {{ $document->id_type }}: {{ $document->original_name }}<span class="sr-only"> ({{ __('opens in a new tab') }})</span>
                    </a>
                    <p class="text-xs text-zinc-500">{{ __('Uploaded :date', ['date' => $document->created_at->format('M j, Y g:i A')]) }}</p>
                    @if ($document->remarks)
                        <p class="mt-1 text-xs text-zinc-700">{{ __('Reason: :remarks', ['remarks' => $document->remarks]) }}</p>
                    @endif
                </div>
                <flux:badge size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge>
            </li>
        @endforeach
    </ul>
@endif
