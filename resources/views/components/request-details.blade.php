@props(['request'])

<dl {{ $attributes->class('grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2') }}>
    <div>
        <dt class="text-zinc-500">{{ __('Credential') }}</dt>
        <dd class="font-medium text-ink">{{ $request->credentialType->name }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Purpose') }}</dt>
        <dd class="font-medium text-ink">{{ $request->purpose }}</dd>
    </div>
    @if ($request->receiving_institution)
        <div class="sm:col-span-2">
            <dt class="text-zinc-500">{{ __('Receiving school') }}</dt>
            <dd class="font-medium text-ink">{{ $request->receiving_institution }}</dd>
        </div>
    @endif
    <div>
        <dt class="text-zinc-500">{{ __('Status') }}</dt>
        <dd class="font-medium text-ink">{{ $request->academic_status->label() }} · {{ $request->attended_from }}–{{ $request->attended_to }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Student number') }}</dt>
        <dd class="font-medium text-ink">{{ $request->student_number ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('College') }}</dt>
        <dd class="font-medium text-ink">{{ $request->college->name }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Program or strand') }}</dt>
        <dd class="font-medium text-ink">{{ $request->program }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Submitted') }}</dt>
        <dd class="font-medium text-ink">{{ $request->submitted_at?->format('M j, Y g:i A') }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Accepted for processing') }}</dt>
        <dd class="font-medium text-ink">{{ $request->accepted_at?->format('M j, Y g:i A') ?? __('Not yet') }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Amount due') }}</dt>
        <dd class="font-medium text-ink">
            @if ($request->requires_payment)
                {{ $request->amount_due !== null ? '₱'.number_format((float) $request->amount_due, 2) : __('Required') }}
            @else
                {{ __('None required') }}
            @endif
        </dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Transacting') }}</dt>
        <dd class="font-medium text-ink">{{ $request->requester_mode->label() }}</dd>
    </div>
</dl>
