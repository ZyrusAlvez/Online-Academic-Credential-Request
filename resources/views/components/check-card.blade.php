{{-- One part of a Registrar stage's checklist, with Confirm / Reject on its right (REQ-16-10). --}}
@props(['item', 'result' => null, 'editable' => true])

@php
    $confirmed = $result === \App\Enums\CheckResult::Confirmed;
    $rejected = $result === \App\Enums\CheckResult::Rejected;
    $headingId = 'check-'.$item->value;
@endphp

<section aria-labelledby="{{ $headingId }}" data-check="{{ $item->value }}" data-result="{{ $result?->value ?? 'unchecked' }}" {{ $attributes->class([
    'rounded-xl border p-5 sm:p-6',
    'border-green-600' => $confirmed,
    'border-red-600' => $rejected,
    'border-secondary/50' => ! $confirmed && ! $rejected,
]) }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <h2 id="{{ $headingId }}" class="text-lg font-semibold text-primary">{{ __($item->label()) }}</h2>
            <p class="text-sm text-zinc-600">{{ __($item->hint()) }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if ($editable)
                {{-- Always green and red so they stand out; the chosen one is ringed (REQ-17-01). --}}
                <flux:button size="sm" icon="check" variant="primary" color="green" data-confirm-button
                    :class="\Illuminate\Support\Arr::toCssClasses(['[--color-accent:var(--color-green-700)]!', 'ring-2 ring-green-700 ring-offset-2' => $confirmed])"
                    wire:click="check('{{ $item->value }}', 'confirmed')" aria-pressed="{{ $confirmed ? 'true' : 'false' }}">
                    {{ __('Confirm') }}
                </flux:button>
                <flux:button size="sm" icon="x-mark" variant="primary" color="red" data-reject-button
                    :class="\Illuminate\Support\Arr::toCssClasses(['[--color-accent:var(--color-red-700)]!', 'ring-2 ring-red-700 ring-offset-2' => $rejected])"
                    wire:click="check('{{ $item->value }}', 'rejected')" aria-pressed="{{ $rejected ? 'true' : 'false' }}">
                    {{ __('Reject') }}
                </flux:button>
            @elseif ($result)
                <flux:badge size="sm" :color="$result->color()">{{ $result->label() }}</flux:badge>
            @endif
        </div>
    </div>

    <div class="mt-4">{{ $slot }}</div>
</section>
