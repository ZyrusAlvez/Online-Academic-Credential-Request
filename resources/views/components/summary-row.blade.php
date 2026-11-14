@props(['label'])

{{-- One entry of an <x-summary-section>: label left, value right; stacked on narrow screens. --}}
<div {{ $attributes->class('py-3 first:pt-0 last:pb-0 sm:grid sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] sm:gap-6') }} data-summary-row>
    <dt class="text-zinc-600">{{ $label }}</dt>
    <dd class="mt-1 font-medium break-words text-ink sm:mt-0">{{ $slot }}</dd>
</div>
