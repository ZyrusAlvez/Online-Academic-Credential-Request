@props(['requests', 'total' => null])

{{-- Every credential of an application, its purpose and fee, then the total due (REQ-27-09). --}}
<div {{ $attributes->class('overflow-hidden rounded-lg border border-secondary/50') }} data-application-credentials>
    <table class="w-full text-sm">
        <thead class="bg-primary-50 text-left text-xs font-medium tracking-wide text-zinc-600 uppercase">
            <tr>
                <th scope="col" class="px-3 py-2">{{ __('Credential') }}</th>
                <th scope="col" class="px-3 py-2 max-sm:hidden">{{ __('Purpose') }}</th>
                <th scope="col" class="px-3 py-2 text-right">{{ __('Fee') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-secondary/30">
            @foreach ($requests as $item)
                <tr wire:key="application-credential-{{ $item->id }}">
                    <td class="px-3 py-2">
                        <div class="font-medium text-ink">{{ $item->credentialType->name }}</div>
                        <div class="text-xs text-zinc-600">{{ $item->reference_no }}@if ($item->receiving_institution) · {{ $item->receiving_institution }}@endif</div>
                        <div class="text-xs text-zinc-600 sm:hidden">{{ $item->purpose }}</div>
                    </td>
                    <td class="px-3 py-2 text-zinc-700 max-sm:hidden">{{ $item->purpose }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap text-ink">{{ $item->fee !== null ? '₱'.number_format((float) $item->fee, 2) : __('None') }}</td>
                </tr>
            @endforeach
        </tbody>
        @if ($total !== null)
            <tfoot class="border-t border-secondary/50">
                <tr>
                    <th scope="row" colspan="2" class="px-3 py-2 text-left font-semibold text-ink max-sm:hidden">{{ __('Total') }}</th>
                    <th scope="row" class="px-3 py-2 text-left font-semibold text-ink sm:hidden">{{ __('Total') }}</th>
                    <td class="px-3 py-2 text-right font-semibold whitespace-nowrap text-ink" data-application-total>₱{{ number_format((float) $total, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
