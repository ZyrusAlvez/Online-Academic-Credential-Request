{{--
    Each documentary requirement with the copies the requester sent (REQ-04-19). Takes the
    request; expects `credentialType` and `requirementDocuments` loaded. Renders nothing when the
    credential lists no requirement and no file was sent.
--}}
@props(['request'])

@php($files = $request->requirementFiles())

@if ($files !== [])
    <ul role="list" {{ $attributes->class('space-y-2 text-sm') }} data-requirement-documents>
        @foreach ($files as $requirement => $documents)
            <li>
                <p class="text-ink">{{ $requirement }}</p>
                @if ($documents->isEmpty())
                    <p class="text-xs text-zinc-500">{{ __('No file uploaded') }}</p>
                @else
                    <ul role="list" class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        @foreach ($documents as $document)
                            <li wire:key="requirement-document-{{ $document->id }}">
                                <x-file-link :href="route('request-documents.show', $document)" :name="$document->original_name" :type="$document->mime_type" class="break-all">
                                    {{ $document->original_name }}
                                </x-file-link>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
@endif
