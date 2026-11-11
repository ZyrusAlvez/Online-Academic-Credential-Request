@props([
    'label',
    'options',
    'value' => null,
    'description' => null,
    'placeholder' => null,
    'searchLabel' => null,
])

{{-- A dropdown whose list opens just below the field, scrolls within a fixed height and can be
     searched (REQ-15-16): the browser's own <select> list of a long set can run off the screen.
     $options is [value => label]. Bind it like an input: <x-select-menu wire:model="field" …>.
     The value changes in the browser (x-modelable) and follows wire:model's modifiers (.live sends it).
     The search box is the ARIA combobox; arrows move, Enter chooses, Escape closes. --}}
@php
    $model = $attributes->wire('model')->value();
    $id = 'menu-'.\Illuminate\Support\Str::slug($model);
    $items = collect($options)->map(fn (string $text, int|string $key): array => ['value' => (string) $key, 'label' => $text])->values();
    $placeholder ??= __('Choose…');
    $chosen = $options[$value] ?? null;
    $invalid = $errors->has($model);
@endphp

<div {{ $attributes->whereStartsWith('wire:model') }} x-modelable="value"
    x-data="selectMenu(@js($items))" x-on:click.outside="open && close()"
    class="relative" data-select-menu>
    <label id="{{ $id }}-label" for="{{ $id }}-button" class="inline-flex items-center text-sm font-medium text-zinc-800">{{ $label }}</label>
    @if ($description)
        <p id="{{ $id }}-description" class="mt-2 text-sm text-zinc-500">{{ $description }}</p>
    @endif

    <button type="button" id="{{ $id }}-button" x-ref="button"
        aria-haspopup="listbox" aria-expanded="false" x-bind:aria-expanded="open" aria-controls="{{ $id }}-panel"
        @if ($description) aria-describedby="{{ $id }}-description" @endif
        @if ($invalid) aria-invalid="true" @endif
        x-on:click="toggle()" x-on:keydown.down.prevent="show()" x-on:keydown.up.prevent="show()"
        @class([
            'mt-3 flex h-10 w-full items-center justify-between gap-2 rounded-lg border bg-white ps-3 pe-2.5 text-start text-base shadow-xs sm:text-sm',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight',
            'border-red-500' => $invalid,
            'border-zinc-200 border-b-zinc-300/80' => ! $invalid,
        ])>
        <span @class(['truncate', 'text-zinc-700' => $chosen, 'text-zinc-400' => ! $chosen])
            x-bind:class="chosen ? 'text-zinc-700' : 'text-zinc-400'"
            x-text="chosen ? chosen.label : @js($placeholder)">{{ $chosen ?? $placeholder }}</span>
        <flux:icon.chevron-up-down variant="mini" class="shrink-0 text-zinc-400" />
    </button>

    {{-- wire:ignore: the list is the browser's; a server re-render must not close or reset it. --}}
    <div id="{{ $id }}-panel" wire:ignore x-show="open" x-cloak style="display: none"
        class="absolute inset-x-0 z-30 mt-1 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg">
        <div class="border-b border-zinc-200 p-2">
            <input type="text" x-ref="search" x-model="query" autocomplete="off"
                role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="{{ $id }}-list"
                x-bind:aria-activedescendant="matches[active] ? '{{ $id }}-option-' + matches[active].value : null"
                aria-label="{{ $searchLabel ?? __('Search') }}" placeholder="{{ $searchLabel ?? __('Search…') }}"
                x-on:input="active = 0"
                x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="choose(matches[active])"
                x-on:keydown.escape.prevent.stop="close(true)"
                x-on:keydown.tab="close()"
                class="h-9 w-full rounded-md border border-zinc-200 px-3 text-base text-zinc-700 placeholder-zinc-400 focus:outline-2 focus:outline-highlight sm:text-sm" />
        </div>
        {{-- mousedown.prevent keeps the focus in the search box while an option is clicked. --}}
        <ul id="{{ $id }}-list" x-ref="list" role="listbox" aria-labelledby="{{ $id }}-label"
            x-on:mousedown.prevent class="max-h-64 overflow-y-auto py-1">
            <template x-for="(option, index) in matches" :key="option.value">
                <li role="option" x-bind:id="'{{ $id }}-option-' + option.value"
                    x-bind:aria-selected="chosen?.value === option.value" x-bind:data-active="index === active"
                    x-on:click="choose(option)" x-on:mousemove="active = index"
                    x-bind:class="index === active ? 'bg-primary-50' : ''"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-ink">
                    <flux:icon.check variant="mini" class="size-4 shrink-0 text-primary" x-bind:class="chosen?.value === option.value ? '' : 'invisible'" />
                    <span x-text="option.label"></span>
                </li>
            </template>
        </ul>
        <p x-show="matches.length === 0" class="px-3 py-2 text-sm text-zinc-500" role="status">{{ __('No match.') }}</p>
    </div>

    <flux:error :name="$model" />
</div>
