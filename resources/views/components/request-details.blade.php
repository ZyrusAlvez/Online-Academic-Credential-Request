@props(['request'])

{{-- A request's details as rows, the field's name left and its value right (REQ-09-22). The slot
     adds rows of the page's own (the valid ID, the documentary requirements) to the same list. --}}
<dl {{ $attributes->class('divide-y divide-zinc-200 text-sm') }} data-request-details>
    <x-summary-row :label="__('Credential')">{{ $request->credentialType->name }}</x-summary-row>
    <x-summary-row :label="__('Purpose')">{{ $request->purpose }}</x-summary-row>
    @if ($request->receiving_institution)
        <x-summary-row :label="__('Receiving school')">{{ $request->receiving_institution }}</x-summary-row>
    @endif
    {{-- The request's own copy of the name (REQ-15-08); the contact number is for the Registrar only. --}}
    <x-summary-row :label="__('Requester')">{{ $request->requester_name }}</x-summary-row>
    <x-summary-row :label="__('Status')">{{ $request->academic_status->label() }} · {{ $request->attended_from }}–{{ $request->attended_to }}</x-summary-row>
    <x-summary-row :label="__('Student number')">
        @if ($request->student_number)
            {{ $request->student_number }}
        @else
            <span class="font-normal text-zinc-600 italic" data-empty-value>{{ __('Not provided') }}</span>
        @endif
    </x-summary-row>
    <x-summary-row :label="__('College')">{{ $request->college->name }}</x-summary-row>
    <x-summary-row :label="__('Program or strand')">{{ $request->program }}</x-summary-row>
    <x-summary-row :label="__('Submitted')">{{ $request->submitted_at?->format('M j, Y g:i A') }}</x-summary-row>
    <x-summary-row :label="__('Accepted for processing')">
        @if ($request->accepted_at)
            {{ $request->accepted_at->format('M j, Y g:i A') }}
        @else
            <span class="font-normal text-zinc-600 italic" data-empty-value>{{ __('Not yet') }}</span>
        @endif
    </x-summary-row>
    <x-summary-row :label="__('Amount due')">
        @if ($request->requires_payment)
            {{ $request->amount_due !== null ? '₱'.number_format((float) $request->amount_due, 2) : __('Required') }}
        @else
            {{ __('None required') }}
        @endif
    </x-summary-row>
    <x-summary-row :label="__('Transacting')">{{ $request->requester_mode->label() }}</x-summary-row>
    {{ $slot }}
</dl>
