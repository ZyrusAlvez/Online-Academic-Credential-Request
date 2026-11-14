{{-- One part of a Registrar stage's checklist, with Reject / Confirm on its right (REQ-16-10).
     `carried`: the part's check when it was confirmed from an earlier verification (REQ-21-05). --}}
@props(['item', 'result' => null, 'editable' => true, 'carried' => null])

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
            @if ($carried)
                {{-- Confirmed already: the requester's details equal the ones verified earlier. --}}
                <p class="mt-2 flex flex-wrap items-center gap-x-1 text-sm text-green-800" data-already-verified>
                    <flux:icon.check-badge variant="mini" class="shrink-0" aria-hidden="true" />
                    <span>{{ __('Already verified by :name on :date', ['name' => $carried->checker?->name, 'date' => $carried->checked_at->format('M j, Y')]) }}</span>
                    @if ($carried->carriedFrom)
                        <span>·</span>
                        <a href="{{ route('records.show', $carried->carriedFrom) }}" wire:navigate class="font-medium underline underline-offset-2 hover:no-underline">{{ $carried->carriedFrom->reference_no }}</a>
                    @endif
                </p>
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if ($editable)
                {{-- Always red and green so they stand out, Confirm on the right; the chosen one is ringed (REQ-17-01). --}}
                <flux:button size="sm" icon="x-mark" variant="primary" color="red" data-reject-button
                    :class="\Illuminate\Support\Arr::toCssClasses(['[--color-accent:var(--color-red-700)]!', 'ring-2 ring-red-700 ring-offset-2' => $rejected])"
                    wire:click="check('{{ $item->value }}', 'rejected')" aria-pressed="{{ $rejected ? 'true' : 'false' }}">
                    {{ __('Reject') }}
                </flux:button>
                <flux:button size="sm" icon="check" variant="primary" color="green" data-confirm-button
                    :class="\Illuminate\Support\Arr::toCssClasses(['[--color-accent:var(--color-green-700)]!', 'ring-2 ring-green-700 ring-offset-2' => $confirmed])"
                    wire:click="check('{{ $item->value }}', 'confirmed')" aria-pressed="{{ $confirmed ? 'true' : 'false' }}">
                    {{ __('Confirm') }}
                </flux:button>
            @elseif ($result)
                <flux:badge size="sm" :color="$result->color()">{{ $result->label() }}</flux:badge>
            @endif
        </div>
    </div>

    <div class="mt-4">{{ $slot }}</div>
</section>
