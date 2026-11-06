@props(['proofs'])

@if ($proofs->isNotEmpty())
    <ul {{ $attributes->class('divide-y divide-secondary/40 rounded-lg border border-secondary/50') }}>
        @foreach ($proofs as $proof)
            <li class="flex flex-wrap items-start justify-between gap-3 p-3 text-sm">
                <div class="min-w-0">
                    <x-file-link :href="route('payment-proofs.show', $proof)" :name="__('Proof of payment: :file', ['file' => $proof->original_name])" :type="$proof->mime_type" class="font-medium break-all">
                        {{ $proof->original_name }}
                    </x-file-link>
                    <p class="text-xs text-zinc-500">
                        {{ __('Uploaded :date', ['date' => $proof->created_at->format('M j, Y g:i A')]) }}
                        @if ($proof->reference_no) · <span class="break-all">{{ __('Ref :ref', ['ref' => $proof->reference_no]) }}</span> @endif
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
