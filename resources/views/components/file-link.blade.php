{{--
    A link to an uploaded file that opens it in <x-file-viewer> (REQ-09-14). It stays a real link:
    without JavaScript, or with Ctrl, ⌘, Shift or a middle click, the file opens in a new tab.
--}}
@props(['href', 'name', 'type', 'download' => null])

<a
    href="{{ $href }}"
    target="_blank"
    rel="noopener"
    aria-haspopup="dialog"
    data-file-link
    x-data
    x-on:click="if ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey || $event.button !== 0) return;
        $event.preventDefault();
        $dispatch('open-file', { url: @js($href), name: @js($name), type: @js($type), download: @js($download) })"
    {{ $attributes->class('text-highlight underline underline-offset-2 hover:no-underline') }}
>{{ $slot }}</a>
