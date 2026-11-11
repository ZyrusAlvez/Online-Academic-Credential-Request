{{-- The requester's personal and academic information and valid ID (REQ-01-16, REQ-15-03, 04),
     on New Request and Settings → Profile.
     Expects: $prefix (the RequesterProfileForm property), $fields (that form), $number (first
     section number, or null), $statuses, $colleges, $programs, $idTypes, $savedId, $verified
     (section → verified, REQ-21-06). --}}
@php($sectionTitle = fn (string $text, int $offset): string => $number !== null ? ($number + $offset).'. '.__($text) : __($text))
@php($verified ??= [])
{{-- Each heading carries, at its right end, whether the saved details are verified; the fields stay
     editable, and Help explains re-verification (REQ-21-02, 06). --}}

<section aria-labelledby="personal-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6" data-section-verification="personal">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <h2 id="personal-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Personal Information', 0) }}</h2>
        <x-verification-badge :verified="$verified['personal'] ?? false" />
    </div>

    <div class="grid gap-6 sm:grid-cols-3">
        <flux:input wire:model="{{ $prefix }}.first_name" :label="__('First name')" autocomplete="given-name" required />
        <flux:input wire:model="{{ $prefix }}.middle_name" :label="__('Middle name (optional)')" autocomplete="additional-name" />
        <flux:input wire:model="{{ $prefix }}.last_name" :label="__('Last name')" autocomplete="family-name" required />
    </div>

    <div class="grid gap-6 sm:grid-cols-3">
        <flux:input wire:model="{{ $prefix }}.contact_number" type="tel" :label="__('Contact number')" autocomplete="tel" inputmode="numeric" maxlength="11" x-mask="99999999999" placeholder="09171234567" required />
    </div>
</section>

<section aria-labelledby="academic-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6" data-section-verification="academic">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <h2 id="academic-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Academic Information', 1) }}</h2>
        <x-verification-badge :verified="$verified['academic'] ?? false" />
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

<section aria-labelledby="identity-heading" class="space-y-5 rounded-xl border border-secondary/50 p-5 sm:p-6" data-section-verification="valid_id">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <h2 id="identity-heading" class="text-lg font-semibold text-primary">{{ $sectionTitle('Valid ID', 2) }}</h2>
        <x-verification-badge :verified="$verified['valid_id'] ?? false" />
    </div>

    {{-- One saved ID at most: while it is saved there are no ID fields; removing it brings them
         back (REQ-15-04, 18). --}}
    @if ($savedId)
        <div class="space-y-2" data-saved-id>
            <h3 class="text-sm font-semibold text-ink">{{ __('Your Saved ID') }}</h3>
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-secondary/50 p-3 text-sm">
                <div class="min-w-0">
                    <x-file-link :href="route('identity-documents.show', $savedId)" :name="$savedId->id_type.': '.$savedId->original_name" :type="$savedId->mime_type" class="font-medium break-all">
                        {{ $savedId->id_type }}: {{ $savedId->original_name }}
                    </x-file-link>
                    <p class="text-xs text-zinc-500">{{ __('Uploaded :date', ['date' => $savedId->created_at->format('M j, Y g:i A')]) }}</p>
                </div>
                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-700!" wire:click="removeSavedId" data-remove-saved-id
                    data-confirm-title="{{ __('Remove saved ID') }}" data-confirm-label="{{ __('Remove') }}" data-confirm-variant="danger" wire:confirm="{{ __('Remove your saved ID? You will need to upload a valid ID again. Requests already sent with it keep it.') }}">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    @else
    <p class="text-sm text-zinc-600">{{ __('A government-issued or school ID (JPG, PNG, WebP or PDF, up to 5 MB).') }}</p>
    <div class="grid gap-6 sm:grid-cols-2">
        <flux:select wire:model="{{ $prefix }}.id_type" :label="__('Type of ID')">
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach ($idTypes as $type)
                <flux:select.option :value="$type">{{ $type }}</flux:select.option>
            @endforeach
        </flux:select>
        <x-file-picker :model="$prefix.'.id_file'" :label="__('Valid ID')" data-id-preview />
    </div>
    @endif
</section>
