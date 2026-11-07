@props(['documents'])

@if ($documents->isNotEmpty())
    <ul {{ $attributes->class('divide-y divide-secondary/40 rounded-lg border border-secondary/50') }}>
        @foreach ($documents as $document)
            <li class="p-3 text-sm">
                <x-file-link :href="route('identity-documents.show', $document)" :name="$document->id_type.': '.$document->original_name" :type="$document->mime_type" class="font-medium break-all">
                    {{ $document->id_type }}: {{ $document->original_name }}
                </x-file-link>
                <p class="text-xs text-zinc-500">{{ __('Uploaded :date', ['date' => $document->created_at->format('M j, Y g:i A')]) }}</p>
            </li>
        @endforeach
    </ul>
@endif
