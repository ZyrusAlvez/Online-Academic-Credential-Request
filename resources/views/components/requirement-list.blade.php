{{--
    The credential's documentary requirements, by name, as one row (REQ-04-22, REQ-09-22). They are
    submitted in person, so there is no file. A row only: place it inside a <dl>, e.g. the slot of
    <x-request-details>. Takes the request; expects `credentialType` loaded. Renders nothing when
    the credential lists no requirement.
--}}
@props(['request'])

@if ($request->credentialType->requirements)
    <x-summary-row :label="__('Documentary requirements')" data-requirement-list>
        <ul role="list" class="space-y-1">
            @foreach ($request->credentialType->requirements as $requirement)
                <li>{{ $requirement }}</li>
            @endforeach
        </ul>
    </x-summary-row>
@endif
