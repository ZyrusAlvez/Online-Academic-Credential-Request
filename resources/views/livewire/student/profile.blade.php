<div class="space-y-8">
    <x-page-header title="My Profile" description="Your academic information and valid ID. The Office of the Registrar uses them to confirm that requests come from the document owner." />

    @unless ($user->canRequestCredentials())
        <flux:callout icon="information-circle" color="sky">
            <flux:callout.heading>{{ __('Before your first request') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Fill in your academic information and upload a valid ID. You can request credentials once both are done.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    <section aria-labelledby="academic-heading" class="space-y-6 rounded-xl border border-secondary/50 bg-white p-5">
        <h2 id="academic-heading" class="text-lg font-semibold text-primary">{{ __('Academic information') }}</h2>

        <form wire:submit="save" class="space-y-6">
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

            <flux:input wire:model="form.contact_number" type="tel" :label="__('Contact number')" autocomplete="tel" inputmode="numeric" maxlength="11" placeholder="09171234567" required />

            <flux:button type="submit" variant="primary">{{ __('Save profile') }}</flux:button>
        </form>
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
            <form wire:submit="uploadId" class="space-y-4">
                <p class="text-sm">{{ __('Upload a clear photo or scan of a valid government-issued or school ID (JPG, PNG, WebP or PDF, up to 5 MB). Only the Office of the Registrar can see it.') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="idType" :label="__('Type of ID')" required>
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($idTypes as $type)
                            <flux:select.option :value="$type">{{ $type }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input type="file" wire:model="idFile" :label="__('Valid ID')" accept=".jpg,.jpeg,.png,.webp,.pdf" required />
                </div>
                <div wire:loading wire:target="idFile" class="text-sm text-zinc-600">{{ __('Uploading…') }}</div>
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="idFile,uploadId">{{ __('Upload ID') }}</flux:button>
            </form>
        @endif

        @if ($documents->isNotEmpty())
            <div class="space-y-2">
                <h3 class="text-sm font-semibold text-ink">{{ __('Uploaded IDs') }}</h3>
                <x-identity-document-list :documents="$documents" />
            </div>
        @endif
    </section>
</div>
