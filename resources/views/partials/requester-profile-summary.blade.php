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

{{-- One row like a requirement's: the type of ID, then the file's name, a link that opens the image
     or PDF in the file viewer, and its size; no picture (REQ-15-05). --}}
<x-summary-section :heading="$sectionTitle('Valid ID', count($summary))" :level="$level">
    @if ($fields->id_file)
        {{-- A signed, short-lived preview of the chosen file; it is only stored on the final save. --}}
        @php($previewUrl = $fields->id_file->temporaryUrl())
        <x-summary-row :label="$fields->id_type" data-id-summary-preview>
            <x-summary-file :href="$previewUrl" :download="$previewUrl" :name="$fields->id_file->getClientOriginalName()" :size="\Illuminate\Support\Number::fileSize((int) $fields->id_file->getSize(), maxPrecision: 1)" :type="(string) $fields->id_file->getMimeType()" />
        </x-summary-row>
    @elseif ($savedId)
        <x-summary-row :label="$savedId->id_type">
            <x-summary-file :href="route('identity-documents.show', $savedId)" :name="$savedId->original_name" :size="\Illuminate\Support\Number::fileSize((int) $savedId->size, maxPrecision: 1)" :type="$savedId->mime_type" />
        </x-summary-row>
    @endif
</x-summary-section>
