<div class="space-y-8">
    <x-page-header title="My Profile" description="Your personal and academic information and valid ID. The Office of the Registrar uses them to confirm that requests come from the document owner." />

    <p id="profile-step" tabindex="-1" class="text-sm font-semibold text-ink outline-none">
        {{ $reviewing ? __('Step 2 of 2: Review and confirm') : __('Step 1 of 2: Your details') }}
    </p>

    @unless ($reviewing || $user->canRequestCredentials())
        <flux:callout icon="information-circle" color="sky">
            <flux:callout.heading>{{ __('Before your first request') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Fill in every section below, upload a valid ID, then choose Confirm. You can request credentials once your profile is complete.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    {{-- Hidden, not removed, on the summary: the file input keeps the chosen ID for "Back to edit".
         novalidate: the browser's popups would stop the submit before the errors modal (REQ-01-26). --}}
    <form wire:submit="review" novalidate @class(['space-y-8', 'hidden' => $reviewing])>
        <section aria-labelledby="personal-heading" class="space-y-6 rounded-xl border border-secondary/50 bg-white p-5">
            <h2 id="personal-heading" class="text-lg font-semibold text-primary">{{ __('Personal information') }}</h2>

            <div class="grid gap-6 sm:grid-cols-3">
                <flux:input wire:model="form.first_name" :label="__('First name')" autocomplete="given-name" :disabled="$user->hasLockedName()" required />
                <flux:input wire:model="form.middle_name" :label="__('Middle name (optional)')" autocomplete="additional-name" :disabled="$user->hasLockedName()" />
                <flux:input wire:model="form.last_name" :label="__('Last name')" autocomplete="family-name" :disabled="$user->hasLockedName()" required />
            </div>

            @if ($user->hasLockedName())
                <p class="text-sm text-zinc-600">{{ __('Your name is locked because your ID is verified. To correct it, contact the Office of the Registrar.') }}</p>
            @endif

            <div class="grid gap-6 sm:grid-cols-3">
                <flux:input wire:model="form.contact_number" type="tel" :label="__('Contact number')" autocomplete="tel" inputmode="numeric" maxlength="11" x-mask="99999999999" placeholder="09171234567" required />
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
                <flux:select wire:model.live="form.college_id" :label="__('College')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($colleges as $college)
                        <flux:select.option :value="$college->id">{{ $college->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div>
                    <flux:select wire:model="form.program" :label="__('Program or strand')" :disabled="$programs->isEmpty()" required>
                        <flux:select.option value="">{{ blank($form->college_id) ? __('Choose a college first') : __('Choose…') }}</flux:select.option>
                        @foreach ($programs as $program)
                            <flux:select.option :value="$program">{{ $program }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if (filled($form->college_id) && $programs->isEmpty())
                        <p class="mt-2 text-sm text-zinc-600">{{ __('No programs are listed for this college yet. Please contact the Office of the Registrar.') }}</p>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <flux:input wire:model="form.student_number" :label="__('Student number (optional)')" autocomplete="off" />
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
                <div class="grid gap-6 sm:grid-cols-2">
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

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="form.id_file,review">{{ __('Confirm') }}</flux:button>
        </div>
    </form>

    @if ($reviewing)
        <div class="space-y-8">
            <flux:text>{{ __('Check that everything below is correct. The Office of the Registrar uses these details to confirm your identity.') }}</flux:text>

            @foreach ($summary as $heading => $rows)
                <section aria-labelledby="summary-heading-{{ $loop->index }}" class="space-y-4 rounded-xl border border-secondary/50 bg-white p-5">
                    <h2 id="summary-heading-{{ $loop->index }}" class="text-lg font-semibold text-primary">{{ __($heading) }}</h2>
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        @foreach ($rows as $label => $value)
                            <div>
                                <dt class="text-zinc-500">{{ __($label) }}</dt>
                                <dd class="font-medium break-words text-ink">{{ __($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach

            <section aria-labelledby="summary-id-heading" class="space-y-4 rounded-xl border border-secondary/50 bg-white p-5">
                <h2 id="summary-id-heading" class="text-lg font-semibold text-primary">{{ __('Valid ID') }}</h2>

                @if ($form->id_file)
                    <dl class="text-sm">
                        <dt class="text-zinc-500">{{ __('New upload') }}</dt>
                        <dd class="font-medium break-all text-ink">{{ $form->id_type }}: {{ $form->id_file->getClientOriginalName() }}</dd>
                    </dl>
                @else
                    <x-identity-document-list :documents="$documents->take(1)" />
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

            <div class="flex flex-wrap justify-end gap-3">
                <flux:button wire:click="back" wire:loading.attr="disabled" wire:target="back,save">{{ __('Back to edit') }}</flux:button>
                <flux:button wire:click="save" variant="primary" wire:loading.attr="disabled" wire:target="back,save">{{ __('Confirm and save') }}</flux:button>
            </div>
        </div>
    @endif

    <flux:modal name="profile-errors" class="w-full md:w-[32rem]" x-on:close="document.querySelector('form [data-invalid]')?.focus()">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Some details need your attention') }}</flux:heading>
            <flux:text>{{ __('Complete or correct the following. The fields are marked in red on the form.') }}</flux:text>
            <ul class="list-disc space-y-1 pl-5 text-sm text-ink">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('OK') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
