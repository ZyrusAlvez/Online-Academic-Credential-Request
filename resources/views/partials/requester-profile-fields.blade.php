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
    <div>
        <h2 id="academic-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Academic Information', 1) }}</h2>
        <p class="mt-1 text-sm text-zinc-600">{{ __('Only the Dean of your college reviews a request, not every college.') }}</p>
    </div>

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
    <div>
        <h2 id="identity-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Valid ID', 2) }}</h2>
        <p class="mt-1 text-sm text-zinc-600">{{ __('The Office of the Registrar checks it against your name to confirm that the request comes from the document owner. Only the Registrar can see it.') }}</p>
    </div>

    @if ($savedId)
        <div class="space-y-2" data-saved-id>
            <h3 class="text-sm font-semibold text-ink">{{ __('Your Saved ID') }}</h3>
            <x-identity-document-list :documents="collect([$savedId])" />
            <p class="text-sm text-zinc-600">{{ __('It is used unless you choose a different one below.') }}</p>
        </div>
    @endif

    <p class="text-sm">{{ $savedId
        ? __('To use a different ID, choose its type and upload a clear photo or scan (JPG, PNG, WebP or PDF, up to 5 MB).')
        : __('Upload a clear photo or scan of a valid government-issued or school ID (JPG, PNG, WebP or PDF, up to 5 MB).') }}</p>
    <div class="grid gap-6 sm:grid-cols-2">
        <flux:select wire:model="{{ $prefix }}.id_type" :label="__('Type of ID')" :badge="$savedId ? __('Optional') : null">
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach ($idTypes as $type)
                <flux:select.option :value="$type">{{ $type }}</flux:select.option>
            @endforeach
        </flux:select>
        {{-- The chosen file opens in the viewer from the browser's own copy: nothing is sent to preview it. --}}
        <div class="space-y-2" data-id-preview
            x-data="{ chosen: null,
                choose(file) {
                    if (this.chosen) URL.revokeObjectURL(this.chosen.url);
                    const url = file ? URL.createObjectURL(file) : null;
                    this.chosen = file ? { url, download: url, name: file.name, type: file.type } : null;
                } }"
            x-on:change="if ($event.target.type === 'file') choose($event.target.files[0] ?? null)">
            <flux:input type="file" wire:model="{{ $prefix }}.id_file" :label="$savedId ? __('Different ID') : __('Valid ID')" :badge="$savedId ? __('Optional') : null" accept=".jpg,.jpeg,.png,.webp,.pdf" />
            <p x-show="chosen" x-cloak class="text-sm text-zinc-600">
                {{ __('Chosen:') }}
                <a href="#" aria-haspopup="dialog" class="break-all font-medium text-highlight underline underline-offset-2 hover:no-underline"
                    x-text="chosen?.name"
                    x-on:click.prevent="$dispatch('open-file', chosen)"></a>
            </p>
        </div>
    </div>
    <div wire:loading wire:target="{{ $prefix }}.id_file" class="text-sm text-zinc-600">{{ __('Uploading…') }}</div>
</section>
