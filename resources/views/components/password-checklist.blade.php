@props(['model' => 'password'])

@php
    // Mirrors Password::defaults() in AppServiceProvider (REQ-01-03, REQ-09-02); the server rule
    // is the real check. Patterns follow Laravel's: \p{Lu}, \p{Ll}, \p{N}, \p{Z}|\p{S}|\p{P}.
    $rules = [
        'length' => __('At least 8 characters'),
        'upper' => __('Has an uppercase letter (A-Z)'),
        'lower' => __('Has a lowercase letter (a-z)'),
        'number' => __('Has a number (0-9)'),
        'symbol' => __('Has a special character (!@#$)'),
    ];
@endphp

<ul
    {{ $attributes->class('space-y-1 text-sm') }}
    data-password-checklist
    x-data="{
        get value() { return String($wire.$get(@js($model)) ?? '') },
        met(rule) {
            const v = this.value;
            return {
                length: [...v].length >= 8,
                upper: /\p{Lu}/u.test(v),
                lower: /\p{Ll}/u.test(v),
                number: /\p{N}/u.test(v),
                symbol: /\p{Z}|\p{S}|\p{P}/u.test(v),
            }[rule];
        },
    }"
>
    @foreach ($rules as $rule => $label)
        <li class="flex items-center gap-2 text-zinc-500" x-bind:class="met('{{ $rule }}') ? 'text-green-700! font-medium' : ''">
            <span class="flex size-4 shrink-0 items-center justify-center" aria-hidden="true">
                <span class="size-2.5 rounded-full border border-current" x-show="! met('{{ $rule }}')"></span>
                <span x-show="met('{{ $rule }}')" x-cloak><flux:icon.check variant="micro" /></span>
            </span>
            <span>{{ $label }}</span>
            <span class="sr-only" x-text="met('{{ $rule }}') ? @js(__('(met)')) : @js(__('(not met)'))"></span>
        </li>
    @endforeach
</ul>
