@props(['value', 'label', 'copied' => null])

{{-- Copies $value to the clipboard and confirms through <x-toast>. --}}
<flux:button
    type="button"
    size="sm"
    variant="ghost"
    icon="clipboard-document"
    :aria-label="$label"
    :tooltip="$label"
    data-copy="{{ $value }}"
    data-copied="{{ $copied ?? __('Copied to clipboard.') }}"
    x-data
    x-on:click="navigator.clipboard.writeText($el.dataset.copy).then(() => $dispatch('notify', { message: $el.dataset.copied }))"
    {{ $attributes }}
/>
