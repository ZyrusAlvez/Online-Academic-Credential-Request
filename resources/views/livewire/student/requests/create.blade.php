<div class="mx-auto max-w-3xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('My Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('New request') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header title="Request a credential" description="Choose the credential, check its requirements, and submit. The Office of the Registrar evaluates every request before accepting it." />

    <x-profile-required-callout :user="$owner" />

    {{-- The client's portal notice (Proposed Workflow, III-D), shown before every submission. --}}
    <flux:callout icon="exclamation-triangle" color="amber">
        <flux:callout.heading>{{ __('Important notice: incomplete requirements') }}</flux:callout.heading>
        <flux:callout.text>
            <span class="block">{{ __('Before submitting your online request for academic credentials, please ensure that your admission requirements and clearance are complete.') }}</span>
            <span class="mt-2 block">{{ __('If your records indicate that you have lacking admission requirements, such as Form 137, the required UPHSL copy of your previous Transcript of Records, a PSA-authenticated birth certificate, or other applicable documents, you must first submit the original required documents to the Registrar\'s Office of the University of Perpetual Help System Laguna (UPHSL). You will also be required to accomplish the appropriate clearance form and complete the necessary clearance process.') }}</span>
            <span class="mt-2 block">{{ __('You must visit the University personally or send an authorized representative on your behalf. A representative must present a valid Special Power of Attorney (SPA) authorizing them to act on your behalf, together with the valid IDs of both the document owner and the representative, subject to University verification.') }}</span>
            <span class="mt-2 block font-medium">{{ __('Your online request will remain pending until the deficiencies have been resolved and verified by the Registrar\'s Office. The prescribed processing period will begin only after all applicable requirements and clearance have been completed, payment has been verified, and the request has been officially accepted. Submitting an online request does not automatically mean that your request has been accepted for processing.') }}</span>
        </flux:callout.text>
    </flux:callout>

    <form wire:submit="submit" class="space-y-8">
        <section aria-labelledby="what-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="what-heading" class="text-lg font-semibold text-primary">{{ __('1. What do you need?') }}</h2>

            <flux:radio.group wire:model.live="form.credential_type_id" :label="__('Credential')" variant="cards" class="grid gap-3 sm:grid-cols-2">
                @foreach ($types as $type)
                    <flux:radio :value="$type->id" class="items-start">
                        <flux:radio.indicator />
                        <div class="flex-1">
                            <flux:heading class="leading-5!">{{ $type->name }}</flux:heading>
                            @if ($type->description)
                                <flux:text size="sm" class="mt-1">{{ $type->description }}</flux:text>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-1">
                                @if ($type->requires_payment)
                                    <flux:badge size="sm" color="amber">{{ $type->fee !== null ? __('₱:fee per copy', ['fee' => number_format((float) $type->fee, 2)]) : __('Requires payment') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green">{{ __('Free') }}</flux:badge>
                                @endif
                                <flux:badge size="sm" color="zinc">{{ trans_choice(':count working day|:count working days', $type->processing_days) }}</flux:badge>
                            </div>
                        </div>
                    </flux:radio>
                @endforeach
            </flux:radio.group>

            @if ($selectedType)
                <div class="space-y-4 rounded-lg bg-primary-50 p-4 text-sm" aria-live="polite">
                    <h3 class="font-semibold text-primary">{{ __('About :name', ['name' => $selectedType->name]) }}</h3>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-zinc-600">{{ __('Fee') }}</dt>
                            <dd class="font-medium text-ink">
                                @if ($selectedType->requires_payment && $selectedType->fee !== null)
                                    {{ __('₱:fee per copy', ['fee' => number_format((float) $selectedType->fee, 2)]) }}
                                    @if (is_numeric($form->copies) && (int) $form->copies > 0)
                                        · {{ __('₱:total for :n', ['total' => number_format((float) $selectedType->fee * (int) $form->copies, 2), 'n' => trans_choice(':count copy|:count copies', (int) $form->copies)]) }}
                                    @endif
                                @else
                                    {{ $selectedType->requires_payment ? __('Requires payment') : __('Free') }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-zinc-600">{{ __('Processing period') }}</dt>
                            <dd class="font-medium text-ink">{{ trans_choice(':count working day|:count working days', $selectedType->processing_days) }} {{ __('from acceptance') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-zinc-600">{{ __('Offices that process it') }}</dt>
                            <dd class="font-medium text-ink">
                                {{ $selectedType->steps->map(fn ($step) => $step->is_student_college ? __('Dean of your college') : $step->department->name.($step->task !== \App\Enums\RouteTask::Prepare ? ' ('.strtolower($step->task->label()).')' : ''))->implode(' → ') }}
                                → {{ __('Registrar (final quality check)') }}
                            </dd>
                        </div>
                        @if ($selectedType->requirements)
                            <div class="sm:col-span-2">
                                <dt class="text-zinc-600">{{ __('Documentary requirements') }}</dt>
                                <dd>
                                    <ul class="mt-1 list-disc space-y-1 ps-5 text-ink">
                                        @foreach ($selectedType->requirements as $requirement)
                                            <li>{{ $requirement }}</li>
                                        @endforeach
                                    </ul>
                                </dd>
                            </div>
                        @endif
                        @if ($selectedType->special_conditions)
                            <div class="sm:col-span-2">
                                <dt class="text-zinc-600">{{ __('Special conditions') }}</dt>
                                <dd class="font-medium text-ink">{{ $selectedType->special_conditions }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Until the profile is complete the credentials can be browsed but not requested (REQ-01-18). --}}
            <div @class(['space-y-6', 'opacity-50' => $locked]) @if ($locked) inert @endif>
            <div class="grid gap-6 sm:grid-cols-[8rem_1fr]">
                <flux:input wire:model.live.debounce.300ms="form.copies" type="number" min="1" max="10" :label="__('Copies')" required />
                <flux:input wire:model="form.purpose" :label="__('Purpose')" :placeholder="__('e.g. Employment, board exam, transfer')" required />
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

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:button :href="route('student.dashboard')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon-trailing="arrow-right" :disabled="$locked" wire:loading.attr="disabled" wire:target="spa,representativeId,submit">{{ __('Submit request') }}</flux:button>
        </div>
        </div>
    </form>
</div>
