@props(['title', 'description' => null])

<header {{ $attributes->class('flex flex-wrap items-end justify-between gap-4') }}>
    <div>
        <h1 class="text-2xl font-bold text-primary">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-zinc-600">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</header>
