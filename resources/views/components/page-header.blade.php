@props(['title', 'description' => null])

<header {{ $attributes->class('flex flex-wrap items-end justify-between gap-4') }}>
    <div>
        <div class="flex flex-wrap items-center gap-1">
            <h1 class="text-2xl font-bold text-primary">{{ $title }}</h1>
            {{ $afterTitle ?? '' }}
        </div>
        @if ($description)
            <div class="mt-1 text-sm text-zinc-600">{{ $description }}</div>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</header>
