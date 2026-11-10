{{-- The requester's personal and academic information and valid ID (REQ-01-16, REQ-15-03, 04),
     on New Request and Settings → Profile.
     Expects: $prefix (the RequesterProfileForm property), $fields (that form), $number (first
     section number, or null), $statuses, $colleges, $programs, $idTypes, $savedId. --}}
@php($sectionTitle = fn (string $text, int $offset): string => $number !== null ? ($number + $offset).'. '.__($text) : __($text))

<section aria-labelledby="personal-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
    <h2 id="personal-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Personal Information', 0) }}</h2>

    <div class="grid gap-6 sm:grid-cols-3">
        <flux:input wire:model="{{ $prefix }}.first_name" :label="__('First name')" autocomplete="given-name" required />
        <flux:input wire:model="{{ $prefix }}.middle_name" :label="__('Middle name (optional)')" autocomplete="additional-name" />
        <flux:input wire:model="{{ $prefix }}.last_name" :label="__('Last name')" autocomplete="family-name" required />
    </div>

    <div class="grid gap-6 sm:grid-cols-3">
        <flux:input wire:model="{{ $prefix }}.contact_number" type="tel" :label="__('Contact number')" autocomplete="tel" inputmode="numeric" maxlength="11" x-mask="99999999999" placeholder="09171234567" required />
    </div>
</section>

<section aria-labelledby="academic-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
    <h2 id="academic-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Academic Information', 1) }}</h2>

    <flux:radio.group wire:model="{{ $prefix }}.academic_status" :label="__('Status')">
        @foreach ($statuses as $status)
            <flux:radio :value="$status->value" :label="$status->label()" />
        @endforeach
    </flux:radio.group>

    <div class="grid gap-6 sm:grid-cols-2">
        <flux:select wire:model.live="{{ $prefix }}.college_id" :label="__('College')" required>
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach ($colleges as $college)
                <flux:select.option :value="$college->id">{{ $college->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <div>
            <flux:select wire:model="{{ $prefix }}.program" :label="__('Program')" :disabled="$programs->isEmpty()" required>
                <flux:select.option value="">{{ blank($fields->college_id) ? __('Choose a college first') : __('Choose…') }}</flux:select.option>
                @foreach ($programs as $program)
                    <flux:select.option :value="$program">{{ $program }}</flux:select.option>
                @endforeach
            </flux:select>
            @if (filled($fields->college_id) && $programs->isEmpty())
                <p class="mt-2 text-sm text-zinc-600">{{ __('No programs are listed for this college yet. Please contact the Office of the Registrar.') }}</p>
            @endif
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <flux:input wire:model="{{ $prefix }}.student_number" :label="__('Student number (optional)')" autocomplete="off" />
        <flux:input wire:model="{{ $prefix }}.attended_from" type="number" min="1950" :max="now()->year" inputmode="numeric" :label="__('First year attended')" placeholder="2020" required />
        <flux:input wire:model="{{ $prefix }}.attended_to" type="number" min="1950" :max="now()->year" inputmode="numeric" :label="__('Last year attended')" placeholder="2024" required />
    </div>
</section>

<section aria-labelledby="identity-heading" class="space-y-5 rounded-xl border border-secondary/50 p-5 sm:p-6">
    <h2 id="identity-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Valid ID', 2) }}</h2>

    @if ($savedId)
        <div class="space-y-2" data-saved-id>
            <h3 class="text-sm font-semibold text-ink">{{ __('Your Saved ID') }}</h3>
            <x-identity-document-list :documents="collect([$savedId])" />
        </div>
    @endif

    <p class="text-sm text-zinc-600">{{ $savedId
        ? __('To use a different ID, upload it below (JPG, PNG, WebP or PDF, up to 5 MB).')
        : __('A government-issued or school ID (JPG, PNG, WebP or PDF, up to 5 MB).') }}</p>
    <div class="grid gap-6 sm:grid-cols-2">
        <flux:select wire:model="{{ $prefix }}.id_type" :label="__('Type of ID')" :badge="$savedId ? __('Optional') : null">
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach ($idTypes as $type)
                <flux:select.option :value="$type">{{ $type }}</flux:select.option>
            @endforeach
        </flux:select>
        {{-- The chosen file opens in the viewer from the browser's own copy: nothing is sent to preview it.
             The browser's picker is hidden, so the name shows once, beside the button (REQ-15-04);
             Livewire's upload events drive the progress bar (REQ-04-18). --}}
        @php($idLabel = $savedId ? __('Different ID') : __('Valid ID'))
        <div class="space-y-2" data-id-preview
            x-data="{ chosen: null, uploading: false, progress: 0,
                choose(file) {
                    if (this.chosen) URL.revokeObjectURL(this.chosen.url);
                    const url = file ? URL.createObjectURL(file) : null;
                    this.chosen = file ? { url, download: url, name: file.name, type: file.type } : null;
                } }"
            x-on:change="if ($event.target.type === 'file') choose($event.target.files[0] ?? null)"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            x-on:livewire-upload-finish="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-cancel="uploading = false">
            <flux:label :badge="$savedId ? __('Optional') : null" aria-hidden="true">{{ $idLabel }}</flux:label>

            <div x-show="! uploading" class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-ink shadow-xs hover:bg-zinc-50 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-highlight">
                    <flux:icon.arrow-up-tray variant="micro" class="text-zinc-500" />
                    <span x-text="chosen ? @js(__('Change file')) : @js(__('Choose file'))">{{ __('Choose file') }}</span>
                    <span class="sr-only">{{ __('for :field', ['field' => $idLabel]) }}</span>
                    <input type="file" wire:model="{{ $prefix }}.id_file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                        @error($prefix.'.id_file') data-invalid aria-invalid="true" @enderror />
                </label>
                <span x-show="! chosen" class="text-sm text-zinc-600">{{ __('No file chosen') }}</span>
                <a href="#" x-show="chosen" x-cloak style="display: none" aria-haspopup="dialog" data-chosen-id
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

            <flux:error :name="$prefix.'.id_file'" />
        </div>
    </div>
</section>
