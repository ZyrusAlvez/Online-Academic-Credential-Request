{{--
    The office's Decision on a document received in its office (To Validate), laid out like the
    registrar's Decision panel: Return on the left, Approve on the right (REQ-24-07, REQ-17-02).
    Forwarding is on the To Forward list (REQ-24-10). Requires
    $visit (with step, department, credentialRequest), $returnOffices.
--}}
@php
    $canReturn = $visit->purpose !== \App\Enums\VisitPurpose::Correction && $returnOffices->isNotEmpty();
@endphp

@can('act', $visit)
    <section aria-labelledby="decision-heading" class="space-y-4 rounded-xl border border-primary bg-primary-50 p-5 sm:p-6" data-office-action>
        <div>
            <h2 id="decision-heading" class="text-lg font-semibold text-primary">{{ __('Decision') }}</h2>
            <p class="text-xs text-zinc-600">{{ __('Received :time', ['time' => $visit->received_at->diffForHumans()]) }}</p>
        </div>

        @if ($visit->step->instructions && $visit->purpose === \App\Enums\VisitPurpose::Route)
            <flux:callout icon="information-circle" color="sky">
                <flux:callout.text>{{ $visit->step->instructions }}</flux:callout.text>
            </flux:callout>
        @endif

        @if ($visit->purpose === \App\Enums\VisitPurpose::Correction)
            @php($returning = $visit->credentialRequest->visits->where('status', \App\Enums\VisitStatus::Returned)->last())
            <flux:callout icon="arrow-uturn-left" color="amber">
                <flux:callout.heading>{{ __('Returned to your office for correction') }}</flux:callout.heading>
                @if ($returning)
                    <flux:callout.text>{{ $returning->return_reason }}@if ($returning->corrective_action) · {{ __('Corrective action: :action', ['action' => $returning->corrective_action]) }}@endif</flux:callout.text>
                @endif
            </flux:callout>
        @endif

        @if ($visit->status === \App\Enums\VisitStatus::Received)
            <form wire:submit="completeVisit" class="space-y-4">
                <flux:textarea wire:model="completeRemarks" :label="__('Remarks')" rows="3" />

                {{-- The approving action on the right (REQ-17-02). --}}
                <div class="flex flex-wrap justify-center gap-2" data-decision-buttons>
                    @if ($canReturn)
                        <flux:modal.trigger name="return-visit">
                            <flux:button type="button" icon="arrow-uturn-left" data-return-button>{{ __('Return') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                    <flux:button type="submit" variant="primary" icon="check" data-approve-button>{{ __('Approve') }}</flux:button>
                </div>
            </form>

            @if ($canReturn)
                <flux:modal name="return-visit" class="w-full md:w-[30rem]">
                    <form wire:submit="returnVisit" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Return for correction') }}</flux:heading>
                        </div>
                        <flux:select wire:model="returnTo" :label="__('Return to')" required>
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($returnOffices as $office)
                                <flux:select.option :value="$office->id">{{ $office->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:textarea wire:model="returnReason" :label="__('Reason for return')" rows="2" required />
                        <flux:textarea wire:model="correctiveAction" :label="__('Corrective action needed')" badge="Optional" rows="2" />
                        <div class="flex justify-end gap-2">
                            <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                            <flux:button type="submit" variant="primary">{{ __('Return document') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @endif
        @endif
    </section>
@endcan
