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
        {{-- Edit Request keeps the credentials the application has (REQ-27-10); New Request offers
             only as many as the requester has room for (REQ-33-04). --}}
        @include('partials.credential-choice', ['several' => ! $editing, 'room' => $room])

        {{-- Sections 2 to 4, filled in from the saved profile (REQ-15-03, 04). No documentary
             requirements are listed (REQ-04-23). --}}
        @include('partials.requester-profile-fields', ['prefix' => 'profile', 'fields' => $profile, 'number' => 2])

        @unless ($editing)
        <section aria-labelledby="rep-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <h2 id="rep-heading" class="text-lg font-semibold text-primary">{{ __('5. Who Will Transact?') }}</h2>

            {{-- Deferred: the choice shows or hides the representative's fields in the browser at once,
                 without a trip to the server (REQ-11-11). --}}
            <flux:radio.group wire:model="form.requester_mode" :label="__('Submitting documents and claiming the credential')">
                @foreach (\App\Enums\RequesterMode::cases() as $mode)
                    <flux:radio :value="$mode->value" :label="$mode === \App\Enums\RequesterMode::Personal ? __('I will transact personally') : __('Through an authorized representative')" />
                @endforeach
            </flux:radio.group>

            <div class="space-y-6" data-representative-fields
                x-show="$wire.form.requester_mode === 'representative'" @if ($form->requester_mode !== 'representative') x-cloak style="display: none" @endif>
                <flux:text>{{ __('They present the originals, with your valid ID, in person. Files: JPG, PNG, WebP or PDF, up to 5 MB each.') }}</flux:text>
                <flux:input wire:model.live.blur="form.representative_name" :label="__('Representative\'s full name')" required />
                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model.live.blur="form.representative_relationship" :label="__('Relationship to you')" badge="Optional" />
                    <flux:input wire:model.live.blur="form.representative_contact" type="tel" :label="__('Representative\'s contact number')" badge="Optional" inputmode="numeric" maxlength="11" placeholder="09171234567" />
                </div>
                {{-- The same picker as the valid ID: the name links to the file viewer (REQ-11-01). --}}
                <div class="grid gap-6 sm:grid-cols-2">
                    <x-file-picker model="spa" :label="__('SPA')" data-spa-picker />
                    <x-file-picker model="representativeId" :label="__('Representative\'s valid ID')" data-representative-id-picker />
                </div>
            </div>
        </section>
        @endunless

        {{-- The client's portal notice (Proposed Workflow, III-D), shown before every submission (REQ-04-02),
             collapsed to its heading (REQ-09-10); wire:ignore.self keeps it open across re-renders. --}}
        <details wire:ignore.self class="group rounded-xl border border-amber-500 bg-amber-50">
            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl p-4 font-medium text-amber-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight [&::-webkit-details-marker]:hidden">
                <flux:icon.exclamation-triangle variant="mini" class="shrink-0 text-amber-600" />
                <span class="flex-1">{{ __('Important notice') }}</span>
                <flux:icon.chevron-down variant="mini" class="shrink-0 text-amber-700 transition-transform group-open:rotate-180" />
            </summary>
            <div class="space-y-2 px-4 pb-4 text-sm text-amber-950 sm:pl-12">
                <p>{{ __('Before submitting your online request for academic credentials, please ensure that your admission requirements and clearance are complete.') }}</p>
                {{-- Plain language (pilot consultation §6). --}}
                <p>{{ __('If your records show that an admission requirement is missing, such as your Form 137, a TOR copy for UPHSL or a PSA-authenticated birth certificate, submit the original to the Registrar\'s Office of the University of Perpetual Help System Laguna (UPHSL) first. You will also need to complete your clearance.') }}</p>
                <p>{{ __('You must visit the University personally or send an authorized representative on your behalf. A representative must present a valid Special Power of Attorney (SPA) authorizing them to act on your behalf, together with the valid IDs of both the document owner and the representative, subject to University verification.') }}</p>
                <p class="font-medium">{{ __('Your request stays pending until the Registrar\'s Office has received and checked what is missing. The processing period starts only upon payment validation, once your requirements and clearance are complete.') }}</p>
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

            @include('partials.requester-profile-summary', ['fields' => $profile, 'number' => 2, 'level' => 2])

            @if ($transactSummary !== [])
                <x-summary-section :heading="__('5. Who Will Transact?')" :rows="$transactSummary" :level="2" />
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
