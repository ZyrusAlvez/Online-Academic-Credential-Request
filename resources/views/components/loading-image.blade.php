{{--
    An <img> with a pulsing placeholder until it has loaded (REQ-09-16). Attributes go to the <img>;
    `placeholder` sizes the placeholder (default: a 16rem by 12rem box).
--}}
@props(['src', 'alt', 'placeholder' => 'h-48 w-64'])

<span class="relative inline-block" x-data="{ loaded: false }" data-loading-image>
    <span x-show="! loaded" class="{{ $placeholder }} flex animate-pulse items-center justify-center rounded-lg bg-zinc-200 text-zinc-400" role="status">
        <flux:icon.photo class="size-8" />
        <span class="sr-only">{{ __('Loading image…') }}</span>
    </span>
    {{-- A cached image may finish before Alpine binds `load`, hence $el.complete. --}}
    <img src="{{ $src }}" alt="{{ $alt }}"
        x-init="if ($el.complete) loaded = true"
        x-on:load="loaded = true"
        x-on:error="loaded = true"
        x-bind:class="loaded ? '' : 'absolute inset-0 opacity-0'"
        {{ $attributes }}>
</span>
