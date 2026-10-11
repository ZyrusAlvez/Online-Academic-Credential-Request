@props(['request'])

<dl {{ $attributes->class('grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2') }}>
    <div>
        <dt class="text-zinc-500">{{ __('Credential') }}</dt>
        <dd class="font-medium text-ink">{{ $request->credentialType->name }} &times; {{ $request->copies }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Purpose') }}</dt>
        <dd class="font-medium text-ink">{{ $request->purpose }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Student number') }}</dt>
        <dd class="font-medium text-ink">{{ $request->student_number }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('College') }}</dt>
        <dd class="font-medium text-ink">{{ $request->college->name }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Program') }}</dt>
        <dd class="font-medium text-ink">{{ $request->program }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Status / last school year') }}</dt>
        <dd class="font-medium text-ink">{{ $request->student_status->label() }} · {{ $request->last_school_year }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Submitted') }}</dt>
        <dd class="font-medium text-ink">{{ $request->created_at->format('M j, Y g:i A') }}</dd>
    </div>
    <div>
        <dt class="text-zinc-500">{{ __('Payment') }}</dt>
        <dd class="font-medium text-ink">
            @if ($request->requires_payment)
                {{ $request->fee !== null ? '₱'.number_format((float) $request->fee, 2) : __('Required') }}
            @else
                {{ __('None required') }}
            @endif
        </dd>
    </div>
</dl>
