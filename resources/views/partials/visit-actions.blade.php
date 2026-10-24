{{-- Office actions on the request's open visit. Requires $visit (with step, department, credentialRequest), $returnOffices. --}}
@can('act', $visit)
    <div class="space-y-3">
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
                    <flux:callout.text>{{ $returning->return_reason }} · {{ __('Corrective action: :action', ['action' => $returning->corrective_action]) }}</flux:callout.text>
                @endif
            </flux:callout>
        @endif

        @if ($visit->status === \App\Enums\VisitStatus::InTransit)
            <p class="text-sm text-zinc-700">{{ __('Forwarded :time. Confirm when the document is physically in your office.', ['time' => $visit->forwarded_at->diffForHumans()]) }}</p>
            <flux:button wire:click="acceptReceipt" variant="primary" icon="inbox-arrow-down" class="w-full">{{ __('Accept receipt') }}</flux:button>
        @else
            <p class="text-xs text-zinc-600">{{ __('Received :time', ['time' => $visit->received_at->diffForHumans()]) }}</p>
            <form wire:submit="completeVisit" class="space-y-3">
                <flux:textarea wire:model="completeRemarks" :label="__('Remarks')" badge="Optional" rows="2" />
                <flux:button type="submit" variant="primary" icon="check" class="w-full">
                    @if ($visit->purpose === \App\Enums\VisitPurpose::Correction)
                        {{ __('Corrected – send back') }}
                    @elseif ($visit->step->task === \App\Enums\RouteTask::FinalCheck)
                        {{ __('Checked – ready for release') }}
                    @else
                        {{ __(':done – release to next office', ['done' => $visit->step->task->doneLabel()]) }}
                    @endif
                </flux:button>
            </form>

            @if ($visit->purpose !== \App\Enums\VisitPurpose::Correction && $returnOffices->isNotEmpty())
                <flux:modal.trigger name="return-visit">
                    <flux:button icon="arrow-uturn-left" class="w-full">{{ __('Return for correction') }}</flux:button>
                </flux:modal.trigger>

                <flux:modal name="return-visit" class="w-full md:w-[30rem]">
                    <form wire:submit="returnVisit" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Return for correction') }}</flux:heading>
                            <flux:text class="mt-2">{{ __('The document goes back to the office you choose. Once corrected, it comes back to you and you receive it again.') }}</flux:text>
                        </div>
                        <flux:select wire:model="returnTo" :label="__('Return to')" required>
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($returnOffices as $office)
                                <flux:select.option :value="$office->id">{{ $office->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:textarea wire:model="returnReason" :label="__('Reason for return')" rows="2" required />
                        <flux:textarea wire:model="correctiveAction" :label="__('Corrective action needed')" rows="2" required />
                        <div class="flex justify-end gap-2">
                            <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                            <flux:button type="submit" variant="primary">{{ __('Return document') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @endif
        @endif
    </div>
@endcan
