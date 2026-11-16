@props(['requests', 'current'])

{{-- One tab per credential of an application; the page's `tab` is the credential's position
     (REQ-27-07, 08). Shown only when there is more than one. --}}
@if ($requests->count() > 1)
    <div role="tablist" aria-label="{{ __('Credentials') }}" class="flex flex-wrap gap-2 border-b border-secondary/50 pb-2" data-credential-tabs>
        @foreach ($requests as $item)
            @php($selected = $item->is($current))
            <button type="button" role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}" wire:click="$set('tab', {{ $item->item_no }})" wire:key="tab-{{ $item->id }}"
                @class([
                    'rounded-lg px-3 py-2 text-left text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight',
                    'bg-primary text-white' => $selected,
                    'text-ink hover:bg-primary-50' => ! $selected,
                ]) data-credential-tab="{{ $item->item_no }}">
                <span class="block font-medium">{{ $item->credentialType->name }}</span>
                <span @class(['block text-xs', 'text-secondary' => $selected, 'text-zinc-600' => ! $selected])>{{ $item->reference_no }}</span>
            </button>
        @endforeach
    </div>
@endif
