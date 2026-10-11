{{-- Requires $step (current step, with department & credentialRequest loaded). --}}
@can('act', $step)
    <div class="space-y-3">
        @if ($step->instructions)
            <flux:callout icon="information-circle" color="sky">
                <flux:callout.text>{{ $step->instructions }}</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="signStep" class="space-y-3">
            <flux:textarea wire:model="signRemarks" :label="__('Remarks')" badge="Optional" rows="2" />
            <flux:button type="submit" variant="primary" icon="pencil" class="w-full">
                {{ __('Sign for :office', ['office' => $step->department->code]) }}
            </flux:button>
        </form>

        @if ($step->status === \App\Enums\StepStatus::OnHold)
            <flux:button wire:click="resumeStep" icon="play" class="w-full">{{ __('Lift hold') }}</flux:button>
        @else
            <flux:modal.trigger name="hold-step">
                <flux:button icon="pause" class="w-full">{{ __('Put on hold') }}</flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    <flux:modal name="hold-step" class="w-full md:w-[28rem]">
        <form wire:submit="holdStep" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Put on hold') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Tell the student what they need to settle. They will see this on their request.') }}</flux:text>
            </div>
            <flux:textarea wire:model="holdRemarks" :label="__('Reason')" :placeholder="__('e.g. Unreturned library book: Data Structures (2nd ed.)')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Put on hold') }}</flux:button>
            </div>
        </form>
    </flux:modal>
@endcan
