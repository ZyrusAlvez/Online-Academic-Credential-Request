<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main id="main-content" tabindex="-1">
        {{-- Wide screens: the content stops at 80rem and is centred; requester pages keep their narrower column (REQ-09-18). --}}
        <div class="mx-auto w-full max-w-7xl" data-page-width>
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.sidebar>
