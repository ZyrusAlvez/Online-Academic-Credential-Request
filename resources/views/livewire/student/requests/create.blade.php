<div class="mx-auto max-w-3xl space-y-6">
    {{-- New Request, or Edit Request for a request returned for clarification (REQ-20-06).
         New Request has no breadcrumb; Edit Request leads back to its request (REQ-09-21). --}}
    @if ($editing)
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('Request Tracker') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('student.requests.show', $editing)" wire:navigate>{{ $editing->reference_no }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    @endif

    {{-- On the summary, "Summary" is the page's title instead (REQ-15-17). --}}
    @unless ($reviewing)
        @if ($editing)
            <x-page-header :title="__('Edit request :ref', ['ref' => $editing->reference_no])" :description="__('Correct your request, then resubmit it.')" />
        @else
            <x-page-header title="Request a credential" description="Fill in the form, review it, and submit." />
        @endif
    @endunless

    {{-- Not shown: it announces the step to screen readers and is the focus target of showStep(). --}}
    <p id="request-step" tabindex="-1" class="sr-only">
        {{ $reviewing ? __('Review and submit your request') : __('Your request') }}
    </p>

    {{-- Hidden, not removed, on the summary: the file inputs keep the chosen files for "Back to edit".
         novalidate: the browser's popups would stop the submit before the errors modal (REQ-01-26). --}}
    <form wire:submit="review" novalidate @class(['space-y-8', 'hidden' => $reviewing])>
        <section aria-labelledby="what-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="what-heading" class="text-lg font-semibold text-primary">{{ __('1. What Do You Need?') }}</h2>

            <x-select-menu wire:model="form.credential_type_id" :label="__('Credential')"
                :options="$types->pluck('name', 'id')->all()" :value="$form->credential_type_id"
                :placeholder="__('Choose a credential…')" :search-label="__('Search credentials')" />

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
                        <span x-text="transfer ? @js(__('Its complete name.')) : @js(__('If the credential is for a specific institution.'))">{{ $selectedType?->requires_receiving_institution ? __('Its complete name.') : __('If the credential is for a specific institution.') }}</span>
                    </flux:description>
                    <flux:input wire:model="form.receiving_institution" x-bind:required="transfer" />
                    <flux:error name="form.receiving_institution" />
                </flux:field>
            </div>
        </section>

        {{-- The chosen credential's requirements, shown in the browser like its card (REQ-15-03, 15). --}}
        @php($chosenId = (string) ($form->credential_type_id ?? ''))
        <section aria-labelledby="requirements-heading" class="space-y-5 rounded-xl border border-secondary/50 p-5 sm:p-6" data-requirements-section>
            <h2 id="requirements-heading" class="text-lg font-semibold text-primary">{{ __('2. Documentary Requirements') }}</h2>

            <div aria-live="polite" class="text-sm">
                <p x-show="String($wire.form.credential_type_id ?? '') === ''" @if ($chosenId !== '') style="display: none" @endif class="text-zinc-600" data-requirements-placeholder>
                    {{ __('Choose a credential first.') }}
                </p>

                @foreach ($types as $type)
                    <div wire:key="requirements-{{ $type->id }}" data-requirement-block="{{ $type->id }}"
                        x-show="String($wire.form.credential_type_id ?? '') === '{{ $type->id }}'" @if ($chosenId !== (string) $type->id) x-cloak style="display: none" @endif>
                        @if ($type->requirements)
                            {{-- One file field per requirement; all optional, so none is marked (REQ-04-18). --}}
                            <div data-requirement-uploads>
                                <p class="text-zinc-600">{{ __('Upload copies if you have them: up to :max files each, JPG, PNG, WebP or PDF, 5 MB per file.', ['max' => \App\Models\RequestDocument::MAX_PER_REQUIREMENT]) }}
                                    @if ($editing)
                                        {{ __('Files you already sent are kept.') }}
                                    @endif
                                </p>
                                {{-- Each requirement lists every file chosen for it, by name, with its own Remove:
                                     choosing files again adds to them, which the browser's picker does not show. --}}
                                @php($max = \App\Models\RequestDocument::MAX_PER_REQUIREMENT)
                                <ul role="list" class="mt-5 space-y-4">
                                    @foreach ($type->requirements as $index => $requirement)
                                        @php($field = "requirementFiles.{$type->id}.{$index}")
                                        @php($files = array_values(array_filter((array) ($requirementFiles[$type->id][$index] ?? []), fn ($file): bool => $file instanceof \Illuminate\Http\UploadedFile)))
                                        {{-- Livewire's upload events bubble up from the picker and drive the progress bar. --}}
                                        <li class="rounded-lg border border-zinc-200 p-4" wire:key="requirement-{{ $type->id }}-{{ $index }}" data-requirement-field="{{ $index }}"
                                            x-data="{ uploading: false, progress: 0 }"
                                            x-on:livewire-upload-start="uploading = true; progress = 0"
                                            x-on:livewire-upload-progress="progress = $event.detail.progress"
                                            x-on:livewire-upload-finish="uploading = false"
                                            x-on:livewire-upload-error="uploading = false"
                                            x-on:livewire-upload-cancel="uploading = false">
                                            {{-- The count stays at the right, however long the requirement's name (REQ-04-18). --}}
                                            <div class="flex items-start justify-between gap-4">
                                                <h3 class="min-w-0 flex-1 font-medium text-ink">{{ $requirement }}</h3>
                                                <span class="shrink-0 whitespace-nowrap text-zinc-600" data-file-count>{{ $files === [] ? __('No files yet') : __(':count of :max files', ['count' => count($files), 'max' => $max]) }}</span>
                                            </div>

                                            @if ($files !== [])
                                                <ul role="list" class="mt-3 divide-y divide-zinc-200 rounded-md border border-zinc-200" data-chosen-files>
                                                    @foreach ($files as $position => $file)
                                                        @php($name = $file->getClientOriginalName())
                                                        <li class="flex items-center gap-3 py-1.5 ps-3 pe-1.5" wire:key="requirement-{{ $type->id }}-{{ $index }}-file-{{ $file->getFilename() }}">
                                                            <flux:icon.document variant="mini" class="shrink-0 text-zinc-500" />
                                                            <span class="min-w-0 flex-1 truncate text-ink" title="{{ $name }}">{{ $name }}</span>
                                                            <span class="shrink-0 text-zinc-500">{{ \Illuminate\Support\Number::fileSize((int) $file->getSize(), maxPrecision: 1) }}</span>
                                                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeRequirementFile({{ $type->id }}, {{ $index }}, {{ $position }})" :aria-label="__('Remove :file', ['file' => $name])">{{ __('Remove') }}</flux:button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif

                                            {{-- While files upload, a progress bar takes the button's place (REQ-04-18). --}}
                                            <div x-show="uploading" x-cloak style="display: none" role="status" class="mt-3 rounded-md border border-secondary/50 bg-primary-50 px-3 py-2.5" data-upload-progress>
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

                                            @if (count($files) < $max)
                                                {{-- The picker itself is hidden: its "No file chosen" or last name is misleading.
                                                     The ring shows for keyboard focus only, not after a click. --}}
                                                <div x-show="! uploading" class="mt-3 flex justify-end">
                                                    <label wire:key="requirement-{{ $type->id }}-{{ $index }}-picker-{{ $requirementResets[$field] ?? 0 }}"
                                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 font-medium text-ink shadow-xs hover:bg-zinc-50 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-highlight">
                                                        <flux:icon.plus variant="mini" class="text-zinc-500" />
                                                        {{ $files === [] ? __('Choose files') : __('Add more files') }}
                                                        <span class="sr-only">{{ __('for :requirement', ['requirement' => $requirement]) }}</span>
                                                        <input type="file" wire:model="{{ $field }}" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only" />
                                                    </label>
                                                </div>
                                            @endif
                                            <flux:error :name="$field" />
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <p class="text-zinc-600">{{ __('No documentary requirements.') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @if ($idRejected)
            {{-- The ID sent with the request was not accepted: another is required (REQ-20-06). --}}
            <flux:callout icon="exclamation-triangle" color="red" data-id-rejected>
                <flux:callout.heading>{{ __('Your valid ID was not accepted') }}</flux:callout.heading>
                <flux:callout.text>{{ $editing->identity_remarks }} {{ __('Upload another valid ID in section 5.') }}</flux:callout.text>
            </flux:callout>
        @endif

        {{-- Sections 3 to 5, filled in from the saved profile (REQ-15-03, 04). --}}
        @include('partials.requester-profile-fields', ['prefix' => 'profile', 'fields' => $profile, 'number' => 3])

        @unless ($editing)
        <section aria-labelledby="rep-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="rep-heading" class="text-lg font-semibold text-primary">{{ __('6. Who Will Transact?') }}</h2>

            <flux:radio.group wire:model.live="form.requester_mode" :label="__('Submitting documents and claiming the credential')">
                @foreach (\App\Enums\RequesterMode::cases() as $mode)
                    <flux:radio :value="$mode->value" :label="$mode === \App\Enums\RequesterMode::Personal ? __('I will transact personally') : __('Through an authorized representative')" />
                @endforeach
            </flux:radio.group>

            @if ($form->requester_mode === 'representative')
                <flux:text>{{ __('They present the originals, with your valid ID, in person. Files: JPG, PNG, WebP or PDF, up to 5 MB each.') }}</flux:text>
                <flux:input wire:model="form.representative_name" :label="__('Representative\'s full name')" required />
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="form.representative_relationship" :label="__('Relationship to you')" badge="Optional" />
                    <flux:input wire:model="form.representative_contact" type="tel" :label="__('Representative\'s contact number')" badge="Optional" inputmode="numeric" maxlength="11" placeholder="09171234567" />
                </div>
                {{-- The same picker as the valid ID: the name links to the file viewer (REQ-11-01). --}}
                <div class="grid gap-6 sm:grid-cols-2">
                    <x-file-picker model="spa" :label="__('SPA')" data-spa-picker />
                    <x-file-picker model="representativeId" :label="__('Representative\'s valid ID')" data-representative-id-picker />
                </div>
            @endif
        </section>
        @endunless

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
            <flux:button :href="$editing ? route('student.requests.show', $editing) : route('student.dashboard')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon-trailing="arrow-right" wire:loading.attr="disabled" wire:target="spa,representativeId,profile.id_file,review">{{ __('Review request') }}</flux:button>
        </div>
    </form>

    @if ($reviewing)
        {{-- The form's sections in the same order and numbers, label left and value right (REQ-15-17). --}}
        <div class="space-y-6">
            <h1 class="text-3xl font-bold text-primary" data-summary-title>{{ __('Summary') }}</h1>

            @foreach ($requestSummary as $heading => $rows)
                <x-summary-section :heading="$heading" :rows="$rows" :level="2" />
            @endforeach

            @include('partials.requester-profile-summary', ['fields' => $profile, 'number' => 3, 'level' => 2])

            @if ($transactSummary !== [])
                <x-summary-section :heading="__('6. Who Will Transact?')" :rows="$transactSummary" :level="2" />
            @endif

            @include('partials.privacy-consent', ['prefix' => 'profile', 'level' => 2])

            @if ($editing)
                <flux:textarea wire:model="note" :label="__('Note to the Office of the Registrar')" badge="Optional" :description="__('What you corrected.')" rows="2" data-resubmit-note />
            @endif

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button wire:click="back" wire:loading.attr="disabled" wire:target="back,submit">{{ __('Back to edit') }}</flux:button>
                <flux:button wire:click="submit" variant="primary" icon-trailing="paper-airplane" x-bind:disabled="! $wire.profile.privacy_consent" :disabled="! $profile->privacy_consent" wire:loading.attr="disabled" wire:target="back,submit" data-submit-request>{{ $editing ? __('Resubmit Request') : __('Submit request') }}</flux:button>
            </div>
        </div>
    @endif

    @include('partials.requester-profile-errors')
</div>
