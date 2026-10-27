<div class="space-y-8">
    <x-page-header title="My Profile" description="Your personal and academic information and valid ID. The Office of the Registrar uses them to confirm that requests come from the document owner." />

    @unless ($user->canRequestCredentials())
        <flux:callout icon="information-circle" color="sky">
            <flux:callout.heading>{{ __('Before your first request') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Fill in every section below, upload a valid ID and save. You can request credentials once your profile is complete.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    <form wire:submit="save" class="space-y-8">
        <section aria-labelledby="personal-heading" class="space-y-6 rounded-xl border border-secondary/50 bg-white p-5">
            <h2 id="personal-heading" class="text-lg font-semibold text-primary">{{ __('Personal information') }}</h2>

            <div class="grid gap-6 sm:grid-cols-3">
                <flux:input wire:model="form.first_name" :label="__('First name')" autocomplete="given-name" required />
                <flux:input wire:model="form.middle_name" :label="__('Middle name')" badge="Optional" autocomplete="additional-name" />
                <flux:input wire:model="form.last_name" :label="__('Last name')" autocomplete="family-name" required />
            </div>

            <div class="sm:max-w-xs">
                <flux:input wire:model="form.contact_number" type="tel" :label="__('Contact number')" autocomplete="tel" inputmode="numeric" maxlength="11" placeholder="09171234567" required />
            </div>
        </section>

        <section aria-labelledby="academic-heading" class="space-y-6 rounded-xl border border-secondary/50 bg-white p-5">
            <h2 id="academic-heading" class="text-lg font-semibold text-primary">{{ __('Academic information') }}</h2>

            <flux:radio.group wire:model="form.academic_status" :label="__('Status')">
                @foreach ($statuses as $status)
                    <flux:radio :value="$status->value" :label="$status->label()" />
                @endforeach
            </flux:radio.group>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:select wire:model="form.college_id" :label="__('College')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($colleges as $college)
                        <flux:select.option :value="$college->id">{{ $college->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.program" :label="__('Program or strand')" :placeholder="__('e.g. BS Computer Science, STEM')" required />
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <flux:input wire:model="form.student_number" :label="__('Student number')" badge="Optional" autocomplete="off" />
                <flux:input wire:model="form.attended_from" type="number" min="1950" :max="now()->year" inputmode="numeric" :label="__('First year attended')" placeholder="2020" required />
                <flux:input wire:model="form.attended_to" type="number" min="1950" :max="now()->year" inputmode="numeric" :label="__('Last year attended')" placeholder="2024" required />
            </div>
        </section>

        <section aria-labelledby="identity-heading" class="space-y-5 rounded-xl border border-secondary/50 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="identity-heading" class="text-lg font-semibold text-primary">{{ __('Valid ID') }}</h2>
                <flux:badge :color="$user->identity_status->color()">{{ $user->identity_status->label() }}</flux:badge>
            </div>

            @if ($user->identity_status === \App\Enums\IdentityStatus::Rejected)
                <flux:callout icon="exclamation-triangle" color="red">
                    <flux:callout.heading>{{ __('Your last ID was not accepted') }}</flux:callout.heading>
                    <flux:callout.text>{{ $documents->first()?->remarks }}</flux:callout.text>
                </flux:callout>
            @elseif ($user->identity_status === \App\Enums\IdentityStatus::Pending)
                <flux:callout icon="clock" color="sky">
                    <flux:callout.text>{{ __('Your ID is being verified by the Office of the Registrar. You can already submit requests; they are accepted for processing once your identity is verified.') }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($canUpload)
                <p class="text-sm">{{ __('Upload a clear photo or scan of a valid government-issued or school ID (JPG, PNG, WebP or PDF, up to 5 MB). Only the Office of the Registrar can see it.') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.id_type" :label="__('Type of ID')">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($idTypes as $type)
                            <flux:select.option :value="$type">{{ $type }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input type="file" wire:model="form.id_file" :label="__('Valid ID')" accept=".jpg,.jpeg,.png,.webp,.pdf" />
                </div>
                <div wire:loading wire:target="form.id_file" class="text-sm text-zinc-600">{{ __('Uploading…') }}</div>
            @endif

            @if ($documents->isNotEmpty())
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Uploaded IDs') }}</h3>
                    <x-identity-document-list :documents="$documents" />
                </div>
            @endif
        </section>

        <section aria-labelledby="privacy-heading" class="space-y-4 rounded-xl border border-secondary/50 bg-white p-5">
            <h2 id="privacy-heading" class="text-lg font-semibold text-primary">{{ __('Data privacy') }}</h2>
            <p class="text-sm">{{ __('The University of Perpetual Help System uses your details, academic information and the valid ID you upload only to process your credential requests, in accordance with the Data Privacy Act of 2012 (RA 10173).') }}</p>

            @if ($user->privacy_consented_at)
                <flux:text size="sm">{{ __('You agreed to the processing of your personal data on :date.', ['date' => $user->privacy_consented_at->format('M j, Y')]) }}</flux:text>
            @else
                <flux:checkbox wire:model="form.privacy_consent" :label="__('I agree to the processing of my personal data')" />
            @endif
        </section>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="form.id_file,save">{{ __('Save profile') }}</flux:button>
        </div>
    </form>
</div>
