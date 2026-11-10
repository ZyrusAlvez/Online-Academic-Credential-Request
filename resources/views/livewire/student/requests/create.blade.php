<div class="mx-auto max-w-3xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('My Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('New request') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header title="Request a credential" description="Choose the credential, give your details and valid ID, review, and submit. Your details are saved for your next request. The Office of the Registrar evaluates every request before accepting it." />

    {{-- Not shown: it announces the step to screen readers and is the focus target of showStep(). --}}
    <p id="request-step" tabindex="-1" class="sr-only">
        {{ $reviewing ? __('Review and submit your request') : __('Your request') }}
    </p>

    {{-- Hidden, not removed, on the summary: the file inputs keep the chosen files for "Back to edit".
         novalidate: the browser's popups would stop the submit before the errors modal (REQ-01-26). --}}
    <form wire:submit="review" novalidate @class(['space-y-8', 'hidden' => $reviewing])>
        <section aria-labelledby="what-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="what-heading" class="text-lg font-semibold text-primary">{{ __('1. What do you need?') }}</h2>

            <flux:select wire:model="form.credential_type_id" :label="__('Credential')" :description="__('Choose one to see its fee, requirements and processing time.')" required>
                <flux:select.option value="">{{ __('Choose a credential…') }}</flux:select.option>
                @foreach ($types as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Every credential's details are on the page and the chosen one is shown in the browser at
                 once, without a trip to the server (REQ-15-15). --}}
            <div aria-live="polite">
            @foreach ($types as $type)
                {{-- The chosen credential's details (REQ-04-02), laid out as a card (REQ-09-11). --}}
                <div wire:key="credential-card-{{ $type->id }}" data-credential-card="{{ $type->id }}"
                    x-show="String($wire.form.credential_type_id ?? '') === '{{ $type->id }}'" @if ((int) $form->credential_type_id !== $type->id) x-cloak style="display: none" @endif
                    class="overflow-hidden rounded-xl border border-secondary/50 bg-white text-sm">
                    <div class="space-y-1 border-b border-secondary/40 bg-primary-50 px-4 py-3 sm:px-5">
                        <h3 class="text-base font-semibold text-primary">{{ $type->name }}</h3>
                        @if ($type->description)
                            <p class="text-ink">{{ $type->description }}</p>
                        @endif
                    </div>

                    <div class="space-y-6 p-4 sm:p-5">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3">
                                <flux:icon.banknotes class="size-5 shrink-0 text-primary" />
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-600">{{ __('Fee') }}</dt>
                                    @if ($type->requires_payment && $type->fee !== null)
                                        <dd class="mt-0.5 font-semibold text-ink">₱{{ number_format((float) $type->fee, 2) }}</dd>
                                    @else
                                        <dd class="mt-0.5 font-semibold text-ink">{{ $type->requires_payment ? __('Requires payment') : __('Free') }}</dd>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3">
                                <flux:icon.clock class="size-5 shrink-0 text-primary" />
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-600">{{ __('Processing period') }}</dt>
                                    <dd class="mt-0.5 font-semibold text-ink">{{ trans_choice(':count working day|:count working days', $type->processing_days) }}</dd>
                                    <dd class="text-zinc-600">{{ __('from acceptance') }}</dd>
                                </div>
                            </div>
                        </dl>

                        <div>
                            <h4 class="font-semibold text-ink">{{ __('Offices that process it') }}</h4>
                            <ol role="list" class="mt-3">
                                @foreach ($routes[$type->id] as $stop)
                                    <li class="relative flex gap-3 pb-4 last:pb-0">
                                        @unless ($loop->last)
                                            <span class="absolute top-7 bottom-1 left-3 w-px -translate-x-1/2 bg-secondary" aria-hidden="true"></span>
                                        @endunless
                                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                                        <span class="pt-0.5 text-ink"><span class="font-medium">{{ $stop['office'] }}</span> <span class="text-zinc-600">({{ strtolower($stop['task']) }})</span></span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        @if ($type->requirements)
                            <div>
                                <h4 class="font-semibold text-ink">{{ __('Documentary requirements') }}</h4>
                                <ul role="list" class="mt-2 space-y-2">
                                    @foreach ($type->requirements as $requirement)
                                        <li class="flex items-start gap-2 text-ink">
                                            <flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-primary" />
                                            <span>{{ $requirement }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($type->special_conditions)
                            <div class="flex items-start gap-2 rounded-lg bg-primary-50 p-3 text-ink">
                                <flux:icon.information-circle variant="mini" class="mt-0.5 shrink-0 text-primary" />
                                <p><span class="font-semibold">{{ __('Special conditions:') }}</span> {{ $type->special_conditions }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>

            <div class="space-y-3">
                <flux:select wire:model.live="form.purpose" :label="__('Purpose')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($purposes as $purpose)
                        <flux:select.option :value="$purpose">{{ $purpose }}</flux:select.option>
                    @endforeach
                    <flux:select.option :value="\App\Livewire\Forms\CredentialRequestForm::OTHER_PURPOSE">{{ __('Others (please specify)') }}</flux:select.option>
                </flux:select>
                @if ($form->purpose === \App\Livewire\Forms\CredentialRequestForm::OTHER_PURPOSE)
                    <flux:input wire:model="form.purpose_other" :label="__('Please specify')" :placeholder="__('Type the purpose of your request')" maxlength="500" required />
                @endif
            </div>

            {{-- One field whose wording follows the chosen credential in the browser (REQ-15-15). --}}
            @php($transferIds = $types->where('requires_receiving_institution', true)->pluck('id')->map(fn ($id) => (string) $id)->values())
            <div data-receiving-school x-data="{ get transfer() { return @js($transferIds).includes(String($wire.form.credential_type_id ?? '')) } }">
                <flux:field>
                    <flux:label>
                        <span x-text="transfer ? @js(__('Receiving school')) : @js(__('Receiving school, employer or agency'))">{{ $selectedType?->requires_receiving_institution ? __('Receiving school') : __('Receiving school, employer or agency') }}</span>
                        {{-- Flux's own "Optional" badge, shown only when the field is optional. --}}
                        <span x-show="! transfer" @if ($selectedType?->requires_receiving_institution) style="display: none" @endif class="ms-1.5 -my-1 rounded-[4px] bg-zinc-800/5 px-1.5 py-1 text-xs text-zinc-800/70" aria-hidden="true">{{ __('Optional') }}</span>
                    </flux:label>
                    <flux:description>
                        <span x-text="transfer ? @js(__('The complete name of the school you are transferring to. It appears on the credential.')) : @js(__('If applicable: the complete name of the institution the credential is for.'))">{{ $selectedType?->requires_receiving_institution ? __('The complete name of the school you are transferring to. It appears on the credential.') : __('If applicable: the complete name of the institution the credential is for.') }}</span>
                    </flux:description>
                    <flux:input wire:model="form.receiving_institution" x-bind:required="transfer" />
                    <flux:error name="form.receiving_institution" />
                </flux:field>
            </div>
        </section>

        {{-- Sections 2 to 4, filled in from the saved profile (REQ-15-03, 04). --}}
        @include('partials.requester-profile-fields', ['prefix' => 'profile', 'fields' => $profile, 'number' => 2])

        <section aria-labelledby="rep-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="rep-heading" class="text-lg font-semibold text-primary">{{ __('5. Who will transact?') }}</h2>

            <flux:radio.group wire:model.live="form.requester_mode" :label="__('Submitting documents and claiming the credential')">
                @foreach (\App\Enums\RequesterMode::cases() as $mode)
                    <flux:radio :value="$mode->value" :label="$mode === \App\Enums\RequesterMode::Personal ? __('I will transact personally') : __('Through an authorized representative')" />
                @endforeach
            </flux:radio.group>

            @if ($form->requester_mode === 'representative')
                <flux:text>{{ __('Upload your representative\'s Special Power of Attorney (SPA) and their valid ID (JPG, PNG, WebP or PDF, up to 5 MB each) so the Registrar can check them. They still present the originals, with your valid ID, in person.') }}</flux:text>
                <flux:input wire:model="form.representative_name" :label="__('Representative\'s full name')" required />
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="form.representative_relationship" :label="__('Relationship to you')" badge="Optional" />
                    <flux:input wire:model="form.representative_contact" type="tel" :label="__('Representative\'s contact number')" badge="Optional" inputmode="numeric" maxlength="11" placeholder="09171234567" />
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input type="file" wire:model="spa" :label="__('SPA')" accept=".jpg,.jpeg,.png,.webp,.pdf" required />
                    <flux:input type="file" wire:model="representativeId" :label="__('Representative\'s valid ID')" accept=".jpg,.jpeg,.png,.webp,.pdf" required />
                </div>
                <div wire:loading wire:target="spa,representativeId" class="text-sm text-zinc-600">{{ __('Uploading…') }}</div>
            @endif
        </section>

        {{-- The client's portal notice (Proposed Workflow, III-D), shown before every submission (REQ-04-02),
             collapsed to its heading (REQ-09-10); wire:ignore.self keeps it open across re-renders. --}}
        <details wire:ignore.self class="group rounded-xl border border-amber-500 bg-amber-50">
            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl p-4 font-medium text-amber-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight [&::-webkit-details-marker]:hidden">
                <flux:icon.exclamation-triangle variant="mini" class="shrink-0 text-amber-600" />
                <span class="flex-1">{{ __('Important notice: incomplete requirements') }}</span>
                <flux:icon.chevron-down variant="mini" class="shrink-0 text-amber-700 transition-transform group-open:rotate-180" />
            </summary>
            <div class="space-y-2 px-4 pb-4 text-sm text-amber-950 sm:pl-12">
                <p>{{ __('Before submitting your online request for academic credentials, please ensure that your admission requirements and clearance are complete.') }}</p>
                <p>{{ __('If your records indicate that you have lacking admission requirements, such as Form 137, the required UPHSL copy of your previous Transcript of Records, a PSA-authenticated birth certificate, or other applicable documents, you must first submit the original required documents to the Registrar\'s Office of the University of Perpetual Help System Laguna (UPHSL). You will also be required to accomplish the appropriate clearance form and complete the necessary clearance process.') }}</p>
                <p>{{ __('You must visit the University personally or send an authorized representative on your behalf. A representative must present a valid Special Power of Attorney (SPA) authorizing them to act on your behalf, together with the valid IDs of both the document owner and the representative, subject to University verification.') }}</p>
                <p class="font-medium">{{ __('Your online request will remain pending until the deficiencies have been resolved and verified by the Registrar\'s Office. The prescribed processing period will begin only after all applicable requirements and clearance have been completed, payment has been verified, and the request has been officially accepted. Submitting an online request does not automatically mean that your request has been accepted for processing.') }}</p>
            </div>
        </details>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:button :href="route('student.dashboard')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon-trailing="arrow-right" wire:loading.attr="disabled" wire:target="spa,representativeId,profile.id_file,review">{{ __('Review request') }}</flux:button>
        </div>
    </form>

    @if ($reviewing)
        <div class="space-y-8">
            <div class="space-y-1">
                <h2 class="text-xl font-bold text-primary" data-summary-title>{{ __('Summary') }}</h2>
                <flux:text>{{ __('Check that everything below is correct. The Office of the Registrar uses these details and your ID to confirm that the request comes from the document owner.') }}</flux:text>
            </div>

            <section aria-labelledby="summary-request-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
                <h3 id="summary-request-heading" class="text-lg font-semibold text-primary">{{ __('Request') }}</h3>
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ($requestSummary as $label => $value)
                        <div>
                            <dt class="text-zinc-500">{{ __($label) }}</dt>
                            <dd class="font-medium break-words text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @include('partials.requester-profile-summary', ['prefix' => 'profile', 'fields' => $profile])

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button wire:click="back" wire:loading.attr="disabled" wire:target="back,submit">{{ __('Back to edit') }}</flux:button>
                <flux:button wire:click="submit" variant="primary" icon-trailing="paper-airplane" x-bind:disabled="! $wire.profile.privacy_consent" :disabled="! $profile->privacy_consent" wire:loading.attr="disabled" wire:target="back,submit" data-submit-request>{{ __('Submit request') }}</flux:button>
            </div>
        </div>
    @endif

    @include('partials.requester-profile-errors')
</div>
