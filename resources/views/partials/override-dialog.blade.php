{{--
    The Admin's Override (spec 35): the credential's next step, recorded for whoever couldn't, with
    when it happened and why. Requires $request (the credential shown), $override (the step the
    dialog was opened for, or null) and $verifiedRepresentatives.
--}}
<flux:modal name="override" class="w-full md:w-[30rem]">
    @if ($override)
        <form wire:submit="recordOverride" class="space-y-6" data-override-dialog>
            <flux:heading size="lg">{{ __($override->label($request)) }}</flux:heading>

            <flux:input wire:model="overrideAt" type="datetime-local" :max="now()->format('Y-m-d\TH:i')" :label="__('When it happened')" required />

            @if ($override === \App\Enums\OverrideStep::ValidatePayment && $request->isWalkIn())
                <flux:input wire:model="overrideReceiptNo" :label="__('Official receipt no.')" maxlength="50" required />
                <flux:input wire:model="overrideAmount" type="number" step="0.01" min="0.01" inputmode="decimal" :label="__('Amount paid')" required />
            @endif

            @if ($override === \App\Enums\OverrideStep::Release)
                @include('partials.release-recipient')
            @endif

            <flux:textarea wire:model="overrideReason" :label="__('Reason')" rows="3" maxlength="500" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Back') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __($override->button()) }}</flux:button>
            </div>
        </form>
    @endif
</flux:modal>
