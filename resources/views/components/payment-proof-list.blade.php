@props(['proofs'])

@if ($proofs->isNotEmpty())
    <ul {{ $attributes->class('divide-y divide-secondary/40 rounded-lg border border-secondary/50') }}>
        @foreach ($proofs as $proof)
            <li class="flex flex-wrap items-start justify-between gap-3 p-3 text-sm">
                <div class="min-w-0">
                    <a href="{{ route('payment-proofs.show', $proof) }}" target="_blank" rel="noopener" class="font-medium break-all text-highlight underline underline-offset-2 hover:no-underline">
                        {{ $proof->original_name }}<span class="sr-only"> ({{ __('opens in a new tab') }})</span>
                    </a>
                    <p class="text-xs text-zinc-500">
                        {{ __('Uploaded :date', ['date' => $proof->created_at->format('M j, Y g:i A')]) }}
                        @if ($proof->reference_no) · {{ __('Ref :ref', ['ref' => $proof->reference_no]) }} @endif
                        @if ($proof->amount !== null) · ₱{{ number_format((float) $proof->amount, 2) }} @endif
                    </p>
                    @if ($proof->remarks)
                        <p class="mt-1 text-xs text-zinc-700">{{ __('Remarks: :remarks', ['remarks' => $proof->remarks]) }}</p>
                    @endif
                </div>
                <flux:badge size="sm" :color="$proof->status->color()">{{ $proof->status->label() }}</flux:badge>
            </li>
        @endforeach
    </ul>
@endif
