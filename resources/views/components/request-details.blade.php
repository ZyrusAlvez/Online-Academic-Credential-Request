@props(['request'])

{{-- A request's details as rows, the field's name left and its value right (REQ-09-22). The slot
     adds rows of the page's own (the valid ID) to the same list. --}}
<dl {{ $attributes->class('divide-y divide-zinc-200 text-sm') }} data-request-details>
    <x-summary-row :label="__('Credential')">{{ $request->credentialType->name }}</x-summary-row>
    <x-summary-row :label="__('Purpose')">{{ $request->purpose }}</x-summary-row>
    @if ($request->receiving_institution)
        <x-summary-row :label="__('Receiving school')">{{ $request->receiving_institution }}</x-summary-row>
    @endif
    {{-- REQ-29-08 --}}
    @if ($request->academic_year)
        <x-summary-row :label="__('Terms')" data-terms>{{ \App\Enums\Term::labels($request->terms) }}</x-summary-row>
        <x-summary-row :label="__('Academic year')">{{ $request->academic_year }}</x-summary-row>
    @endif
    {{-- The request's own copy of the name (REQ-15-08); the contact number is for the Registrar only. --}}
    <x-summary-row :label="__('Requester')">{{ $request->requester_name }}</x-summary-row>
    @if ($request->maiden_name)
        <x-summary-row :label="__('Maiden name')">{{ $request->maiden_name }}</x-summary-row>
    @endif
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
    {{-- This credential's fee, marked paid once the payment is verified, which is when a paid
         request is accepted (REQ-09-23); an application's total is with its payment (REQ-27-09). --}}
    <x-summary-row :label="__('Fee')" data-fee>
        @if ($request->fee !== null)
            ₱{{ number_format((float) $request->fee, 2) }}
            @if ($request->accepted_at)
                <flux:badge size="sm" color="green" class="ms-1" data-paid>{{ __('Paid') }}</flux:badge>
            @endif
        @else
            {{ __('None required') }}
        @endif
    </x-summary-row>
    <x-summary-row :label="__('Transacting')">{{ $request->requester_mode->label() }}</x-summary-row>
    {{ $slot }}
</dl>
