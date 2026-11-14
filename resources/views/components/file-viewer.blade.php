{{--
    Shows an uploaded file on the page (REQ-09-14): an image fitted to the screen, a PDF inline, or
    a download for anything else, with a spinner until it has loaded. <x-file-link> dispatches
    `open-file` with the URL, the name and the MIME type. Esc, focus trap and focus return come from
    the Flux modal.
--}}
<div
    data-file-viewer
    x-data="{ url: null, name: '', type: '', loading: false, failed: false,
        get isImage() { return this.type.startsWith('image/') },
        get isPdf() { return this.type === 'application/pdf' },
        {{-- A file not saved yet (a browser copy or a signed preview) brings its own download address. --}}
        download: null,
        get downloadUrl() { return ! this.url ? '#' : this.download ?? this.url + (this.url.includes('?') ? '&' : '?') + 'download=1' },
        {{-- A PDF is shown from the browser's copy: Livewire's signed preview of a file not sent yet
             answers as a download, which a frame would not show. A browser copy (blob:) is used as is. --}}
        pdfUrl: null,
        async loadPdf(url) {
            if (url.startsWith('blob:')) { this.pdfUrl = url; return; }
            try {
                const response = await fetch(url, { credentials: 'same-origin' });
                if (! response.ok) throw new Error(response.status);
                const copy = new Blob([await response.blob()], { type: 'application/pdf' });
                if (this.url === url) this.pdfUrl = URL.createObjectURL(copy);
            } catch (error) {
                if (this.url === url) { this.loading = false; this.failed = true; }
            }
        },
        clearPdf() {
            if (this.pdfUrl && this.pdfUrl !== this.url) URL.revokeObjectURL(this.pdfUrl);
            this.pdfUrl = null;
        },
        open(detail) {
            this.clearPdf();
            this.url = detail.url; this.name = detail.name; this.type = detail.type || ''; this.download = detail.download ?? null;
            this.failed = false;
            this.loading = this.isImage || this.isPdf;
            if (this.isPdf) this.loadPdf(this.url);
            $flux.modal('file-viewer').show();
        } }"
    x-on:open-file.window="open($event.detail)"
>
    <flux:modal name="file-viewer" class="w-full max-w-5xl" x-on:close="clearPdf(); url = null; loading = false">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3 pe-8">
                <flux:heading size="lg" class="min-w-0 break-all" x-text="name"></flux:heading>
                <div class="flex flex-wrap gap-2">
                    {{-- href="#" makes Flux render a link; Alpine sets the real address. --}}
                    <flux:button size="sm" icon="arrow-down-tray" href="#" x-bind:href="downloadUrl" data-file-download>{{ __('Download') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" href="#" x-bind:href="pdfUrl ?? url ?? '#'" target="_blank" rel="noopener">{{ __('Open in new tab') }}</flux:button>
                </div>
            </div>

            <div class="relative flex max-h-[75vh] min-h-64 items-center justify-center overflow-auto rounded-lg border border-secondary/50 bg-zinc-50" x-bind:aria-busy="loading.toString()">
                <div x-show="loading" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-sm text-zinc-600" role="status" data-file-loading>
                    <flux:icon.loading class="size-8 text-primary" />
                    <span>{{ __('Loading…') }}</span>
                </div>

                {{-- An image already in the cache may finish before Alpine binds `load`, hence $el.complete. --}}
                <template x-if="url && isImage && ! failed">
                    <img x-bind:src="url" x-bind:alt="name"
                        x-init="if ($el.complete && $el.naturalWidth) loading = false"
                        x-on:load="loading = false"
                        x-on:error="loading = false; failed = true"
                        x-bind:class="loading ? 'opacity-0' : 'opacity-100'"
                        class="max-h-[73vh] max-w-full object-contain transition-opacity duration-200">
                </template>
                <template x-if="pdfUrl && isPdf && ! failed">
                    <iframe x-bind:src="pdfUrl" x-bind:title="name" x-on:load="loading = false" class="h-[73vh] w-full bg-white"></iframe>
                </template>
                <template x-if="url && failed">
                    <p class="p-6 text-sm text-zinc-600" role="alert">{{ __('This file couldn\'t be loaded. Try Download or Open in new tab.') }}</p>
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
