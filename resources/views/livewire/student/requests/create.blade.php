<div class="mx-auto max-w-3xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('My Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('New request') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header title="Request a credential" description="Choose the credential, check its requirements, and submit. The Office of the Registrar evaluates every request before accepting it." />

    <x-profile-required-callout :user="$owner" />

    <form wire:submit="submit" class="space-y-8">
        <section aria-labelledby="what-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="what-heading" class="text-lg font-semibold text-primary">{{ __('1. What do you need?') }}</h2>

            <flux:select wire:model.live="form.credential_type_id" :label="__('Credential')" :description="__('Choose one to see its fee, requirements and processing time.')" required>
                <flux:select.option value="">{{ __('Choose a credential…') }}</flux:select.option>
                @foreach ($types as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($selectedType)
                {{-- The chosen credential's details (REQ-04-02), laid out as a card (REQ-09-11). --}}
                <div class="overflow-hidden rounded-xl border border-secondary/50 bg-white text-sm" aria-live="polite">
                    <div class="space-y-1 border-b border-secondary/40 bg-primary-50 px-4 py-3 sm:px-5">
                        <h3 class="text-base font-semibold text-primary">{{ $selectedType->name }}</h3>
                        @if ($selectedType->description)
                            <p class="text-ink">{{ $selectedType->description }}</p>
                        @endif
                    </div>

                    <div class="space-y-6 p-4 sm:p-5">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3">
                                <flux:icon.banknotes class="size-5 shrink-0 text-primary" />
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-600">{{ __('Fee') }}</dt>
                                    @if ($selectedType->requires_payment && $selectedType->fee !== null)
                                        <dd class="mt-0.5 font-semibold text-ink">{{ __('₱:fee per copy', ['fee' => number_format((float) $selectedType->fee, 2)]) }}</dd>
                                        @if (is_numeric($form->copies) && (int) $form->copies > 0)
                                            <dd class="text-zinc-600">{{ __('₱:total for :n', ['total' => number_format((float) $selectedType->fee * (int) $form->copies, 2), 'n' => trans_choice(':count copy|:count copies', (int) $form->copies)]) }}</dd>
                                        @endif
                                    @else
                                        <dd class="mt-0.5 font-semibold text-ink">{{ $selectedType->requires_payment ? __('Requires payment') : __('Free') }}</dd>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3">
                                <flux:icon.clock class="size-5 shrink-0 text-primary" />
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-600">{{ __('Processing period') }}</dt>
                                    <dd class="mt-0.5 font-semibold text-ink">{{ trans_choice(':count working day|:count working days', $selectedType->processing_days) }}</dd>
                                    <dd class="text-zinc-600">{{ __('from acceptance') }}</dd>
                                </div>
                            </div>
                        </dl>

                        <div>
                            <h4 class="font-semibold text-ink">{{ __('Offices that process it') }}</h4>
                            <ol role="list" class="mt-3">
                                @foreach ($route as $stop)
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

                        @if ($selectedType->requirements)
                            <div>
                                <h4 class="font-semibold text-ink">{{ __('Documentary requirements') }}</h4>
                                <ul role="list" class="mt-2 space-y-2">
                                    @foreach ($selectedType->requirements as $requirement)
                                        <li class="flex items-start gap-2 text-ink">
                                            <flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-primary" />
                                            <span>{{ $requirement }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($selectedType->special_conditions)
                            <div class="flex items-start gap-2 rounded-lg bg-primary-50 p-3 text-ink">
                                <flux:icon.information-circle variant="mini" class="mt-0.5 shrink-0 text-primary" />
                                <p><span class="font-semibold">{{ __('Special conditions:') }}</span> {{ $selectedType->special_conditions }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Until the profile is complete the credentials can be browsed but not requested (REQ-01-18). --}}
            <div @class(['space-y-6', 'opacity-50' => $locked]) @if ($locked) inert @endif>
            <div class="grid gap-6 sm:grid-cols-[8rem_1fr]">
                <flux:input wire:model.live.debounce.300ms="form.copies" type="number" min="1" max="10" :label="__('Copies')" required />
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
            </div>

            @if ($selectedType?->requires_receiving_institution)
                <flux:input wire:model="form.receiving_institution" :label="__('Receiving school')" :description="__('The complete name of the school you are transferring to. It appears on the credential.')" required />
            @else
                <flux:input wire:model="form.receiving_institution" :label="__('Receiving school, employer or agency')" :description="__('If applicable: the complete name of the institution the credential is for.')" badge="Optional" />
            @endif
            </div>
        </section>

        <div @class(['space-y-8', 'opacity-50' => $locked]) @if ($locked) inert @endif>

        <section aria-labelledby="who-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="who-heading" class="text-lg font-semibold text-primary">{{ __('2. Your academic details') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600">{{ __('Taken from your profile. Only the Dean of your college reviews the request, not every college.') }}</p>
                </div>
                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('student.profile')" wire:navigate>{{ __('Edit in My Profile') }}</flux:button>
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-zinc-500">{{ __('Status') }}</dt><dd class="font-medium text-ink">{{ $owner->academic_status?->label() }} · {{ $owner->attended_from }}–{{ $owner->attended_to }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('College') }}</dt><dd class="font-medium text-ink">{{ $owner->college?->name }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Program or strand') }}</dt><dd class="font-medium text-ink">{{ $owner->program }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Student number') }}</dt><dd class="font-medium text-ink">{{ $owner->student_number ?? '—' }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="rep-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="rep-heading" class="text-lg font-semibold text-primary">{{ __('3. Who will transact?') }}</h2>

            <flux:radio.group wire:model.live="form.requester_mode" :label="__('Submitting documents and claiming the credential')">
                @foreach (\App\Enums\RequesterMode::cases() as $mode)
                    <flux:radio :value="$mode->value" :label="$mode === \App\Enums\RequesterMode::Personal ? __('I will transact personally') : __('Through an authorized representative')" />
                @endforeach
            </flux:radio.group>

            @if ($form->requester_mode === 'representative')
                <flux:text>{{ __('Your representative must present a Special Power of Attorney (SPA) and the valid IDs of both of you. You may upload the SPA and their ID now so the Registrar can check them ahead; the originals are still presented in person.') }}</flux:text>
                <flux:input wire:model="form.representative_name" :label="__('Representative\'s full name')" required />
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="form.representative_relationship" :label="__('Relationship to you')" badge="Optional" />
                    <flux:input wire:model="form.representative_contact" type="tel" :label="__('Representative\'s contact number')" badge="Optional" inputmode="numeric" maxlength="11" placeholder="09171234567" />
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input type="file" wire:model="spa" :label="__('SPA')" badge="Optional" accept=".jpg,.jpeg,.png,.webp,.pdf" />
                    <flux:input type="file" wire:model="representativeId" :label="__('Representative\'s valid ID')" badge="Optional" accept=".jpg,.jpeg,.png,.webp,.pdf" />
                </div>
                <div wire:loading wire:target="spa,representativeId" class="text-sm text-zinc-600">{{ __('Uploading…') }}</div>
            @endif
        </section>
        </div>

        {{-- The client's portal notice (Proposed Workflow, III-D), shown before every submission (REQ-04-02),
             collapsed to its heading (REQ-09-10). Outside the locked block so it can always be opened;
             wire:ignore.self keeps it open across re-renders. --}}
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
            <flux:button type="submit" variant="primary" icon-trailing="arrow-right" :disabled="$locked" wire:loading.attr="disabled" wire:target="spa,representativeId,submit">{{ __('Submit request') }}</flux:button>
        </div>
    </form>
</div>
