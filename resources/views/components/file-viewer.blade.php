{{--
    Shows an uploaded file on the page (REQ-09-14): an image fitted to the screen that zooms to full
    size, a PDF inline, or a download for anything else. <x-file-link> dispatches `open-file` with
    the URL, the name and the MIME type. Esc, focus trap and focus return come from the Flux modal.
--}}
<div
    data-file-viewer
    x-data="{ url: null, name: '', type: '', zoomed: false,
        get isImage() { return this.type.startsWith('image/') },
        get isPdf() { return this.type === 'application/pdf' },
        get downloadUrl() { return this.url ? this.url + (this.url.includes('?') ? '&' : '?') + 'download=1' : '#' },
        open(detail) {
            this.url = detail.url; this.name = detail.name; this.type = detail.type || ''; this.zoomed = false;
            $flux.modal('file-viewer').show();
        } }"
    x-on:open-file.window="open($event.detail)"
>
    <flux:modal name="file-viewer" class="w-full max-w-5xl" x-on:close="url = null">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3 pe-8">
                <flux:heading size="lg" class="min-w-0 break-all" x-text="name"></flux:heading>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" x-show="isImage" x-on:click="zoomed = ! zoomed" x-bind:aria-pressed="zoomed.toString()">
                        <span x-show="! zoomed">{{ __('Zoom to full size') }}</span>
                        <span x-show="zoomed">{{ __('Fit to screen') }}</span>
                    </flux:button>
                    {{-- href="#" makes Flux render a link; Alpine sets the real address. --}}
                    <flux:button size="sm" icon="arrow-down-tray" href="#" x-bind:href="downloadUrl" data-file-download>{{ __('Download') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" href="#" x-bind:href="url ?? '#'" target="_blank" rel="noopener">{{ __('Open in new tab') }}</flux:button>
                </div>
            </div>

            <div class="flex max-h-[75vh] min-h-48 items-center justify-center overflow-auto rounded-lg border border-secondary/50 bg-zinc-50" x-bind:class="zoomed ? 'items-start justify-start' : ''">
                <template x-if="url && isImage">
                    <img x-bind:src="url" x-bind:alt="name" x-on:click="zoomed = ! zoomed"
                        x-bind:class="zoomed ? 'max-w-none cursor-zoom-out' : 'max-h-[73vh] max-w-full object-contain cursor-zoom-in'">
                </template>
                <template x-if="url && isPdf">
                    <iframe x-bind:src="url" x-bind:title="name" class="h-[73vh] w-full bg-white"></iframe>
                </template>
                <template x-if="url && ! isImage && ! isPdf">
                    <p class="p-6 text-sm text-zinc-600">{{ __('This file can\'t be shown here. Use Download to open it.') }}</p>
                </template>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
