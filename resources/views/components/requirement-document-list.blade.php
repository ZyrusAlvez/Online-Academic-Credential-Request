{{--
    Each documentary requirement with the copies the requester sent (REQ-04-19), one row per
    requirement: its name left, the files right (REQ-09-22). Rows only: place it inside a <dl>,
    e.g. the slot of <x-request-details>. Takes the request; expects `credentialType` and
    `requirementDocuments` loaded. Renders nothing when the credential lists no requirement and no
    file was sent.
--}}
@props(['request'])

@foreach ($request->requirementFiles() as $requirement => $documents)
    <x-summary-row :label="$requirement" data-requirement-document>
        @if ($documents->isEmpty())
            <span class="font-normal text-zinc-600 italic" data-empty-value>{{ __('No file uploaded') }}</span>
        @else
            <ul role="list" class="space-y-1">
                @foreach ($documents as $document)
                    <li wire:key="requirement-document-{{ $document->id }}">
                        <x-file-link :href="route('request-documents.show', $document)" :name="$document->original_name" :type="$document->mime_type" class="break-all">
                            {{ $document->original_name }}
                        </x-file-link>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-summary-row>
@endforeach
