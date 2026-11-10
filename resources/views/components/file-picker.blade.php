@props(['model', 'label'])

{{--
    One file field: a Choose file / Change file button, then the chosen file's name, a link that
    opens it in the file viewer from the browser's own copy (nothing is sent to preview it). The
    browser's picker is hidden, so the name shows once; Livewire's upload events drive the progress
    bar (REQ-15-04, REQ-11-01, REQ-04-18). The name clears when the component resets the field.
--}}
<div {{ $attributes->class('space-y-2') }}
    x-data="{ chosen: null, uploading: false, progress: 0,
        choose(file) {
            if (this.chosen) URL.revokeObjectURL(this.chosen.url);
            const url = file ? URL.createObjectURL(file) : null;
            this.chosen = file ? { url, download: url, name: file.name, type: file.type } : null;
        } }"
    x-init="$wire.$watch(@js($model), (value) => { if (! value) choose(null) })"
    x-on:change="if ($event.target.type === 'file') choose($event.target.files[0] ?? null)"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-cancel="uploading = false">
    <flux:label aria-hidden="true">{{ $label }}</flux:label>

    <div x-show="! uploading" class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-ink shadow-xs hover:bg-zinc-50 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-highlight">
            <flux:icon.arrow-up-tray variant="micro" class="text-zinc-500" />
            <span x-text="chosen ? @js(__('Change file')) : @js(__('Choose file'))">{{ __('Choose file') }}</span>
            <span class="sr-only">{{ __('for :field', ['field' => $label]) }}</span>
            <input type="file" wire:model="{{ $model }}" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                @error($model) data-invalid aria-invalid="true" @enderror />
        </label>
        <span x-show="! chosen" class="text-sm text-zinc-600">{{ __('No file chosen') }}</span>
        <a href="#" x-show="chosen" x-cloak style="display: none" aria-haspopup="dialog" data-chosen-file
            class="min-w-0 break-all text-sm font-medium text-highlight underline underline-offset-2 hover:no-underline"
            x-text="chosen?.name"
            x-on:click.prevent="$dispatch('open-file', chosen)"></a>
    </div>

    <div x-show="uploading" x-cloak style="display: none" role="status" class="rounded-md border border-secondary/50 bg-primary-50 px-3 py-2.5 text-sm" data-upload-progress>
        <div class="flex items-center justify-between gap-3">
            <span class="flex items-center gap-2 font-medium text-primary">
                <flux:icon.loading variant="mini" />
                {{ __('Uploading…') }}
            </span>
            <span class="tabular-nums text-zinc-600" x-text="progress + '%'">0%</span>
        </div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-secondary/40" aria-hidden="true">
            <div class="h-full rounded-full bg-primary transition-[width] duration-200" x-bind:style="{ width: progress + '%' }" style="width: 0%"></div>
        </div>
    </div>

    <flux:error :name="$model" />
</div>
