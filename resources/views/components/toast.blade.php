{{-- Listens for Livewire `$this->dispatch('notify', message: '...')` and session('notify'). --}}
<div
    x-data="{ show: false, message: '', timer: null,
        open(message) { this.message = message; this.show = true; clearTimeout(this.timer); this.timer = setTimeout(() => this.show = false, 5000) } }"
    x-init="@if (session('notify')) open(@js(session('notify'))) @endif"
    x-on:notify.window="open($event.detail.message)"
    role="status"
    aria-live="polite"
    class="pointer-events-none fixed inset-x-4 top-4 z-50 flex justify-center"
>
    <div
        x-show="show"
        x-transition.opacity
        x-cloak
        class="pointer-events-auto flex max-w-sm items-start gap-3 rounded-lg bg-primary px-4 py-3 text-sm text-white shadow-lg"
    >
        <span x-text="message"></span>
        <button type="button" x-on:click="show = false" class="-m-1 rounded p-1 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white" aria-label="Dismiss">
            <flux:icon.x-mark class="size-4" />
        </button>
    </div>
</div>
