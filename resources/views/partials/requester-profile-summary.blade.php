{{-- The summary of the requester's details and the ID that will be used (REQ-15-05, REQ-15-07),
     numbered like the form when $number is given (REQ-15-17); the consent is
     partials/privacy-consent. Expects: $fields (the RequesterProfileForm), $summary, $savedId;
     optional $number and $level (the sections' heading level, 3 by default). --}}
@php($number ??= null)
@php($level ??= 3)
@php($sectionTitle = fn (string $text, int $offset): string => $number !== null ? ($number + $offset).'. '.__($text) : __($text))

@foreach ($summary as $heading => $rows)
    <x-summary-section :heading="$sectionTitle($heading, $loop->index)" :level="$level" :rows="collect($rows)->mapWithKeys(fn ($value, $label) => [__($label) => __($value)])->all()" />
@endforeach

<x-summary-section :heading="$sectionTitle('Valid ID', count($summary))" :level="$level">
    @if ($fields->id_file)
        {{-- A signed, short-lived preview of the chosen file; it is only stored on the final save. --}}
        @php($previewUrl = $fields->id_file->temporaryUrl())
        @php($previewName = $fields->id_file->getClientOriginalName())
        @php($previewType = (string) $fields->id_file->getMimeType())
        <x-summary-row :label="__('Type of ID')">{{ $fields->id_type }}</x-summary-row>
        <x-summary-row :label="__('New upload')" data-id-summary-preview>
            <span class="break-all">{{ $previewName }}</span>
            <div class="mt-3">
                @if (str_starts_with($previewType, 'image/'))
                    <x-file-link :href="$previewUrl" :download="$previewUrl" :name="$previewName" :type="$previewType" class="inline-block no-underline">
                        <img src="{{ $previewUrl }}" alt="{{ __('Your chosen ID (open larger)') }}" class="max-h-64 max-w-full rounded-lg border border-secondary/50 object-contain">
                    </x-file-link>
                @else
                    <x-file-link :href="$previewUrl" :download="$previewUrl" :name="$previewName" :type="$previewType" class="font-medium">{{ __('View PDF') }}</x-file-link>
                @endif
            </div>
        </x-summary-row>
    @elseif ($savedId)
        <x-summary-row :label="__('Type of ID')">{{ $savedId->id_type }}</x-summary-row>
        <x-summary-row :label="__('File')">
            <x-file-link :href="route('identity-documents.show', $savedId)" :name="$savedId->id_type.': '.$savedId->original_name" :type="$savedId->mime_type" class="font-medium break-all">{{ $savedId->original_name }}</x-file-link>
            <p class="text-xs font-normal text-zinc-500">{{ __('Uploaded :date', ['date' => $savedId->created_at->format('M j, Y g:i A')]) }}</p>
        </x-summary-row>
    @endif
</x-summary-section>
