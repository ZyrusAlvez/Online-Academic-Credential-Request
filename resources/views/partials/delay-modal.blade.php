{{-- "Record delay reason" button and modal (REQ-05-09). --}}
<flux:modal.trigger name="record-delay">
    <flux:button variant="ghost" icon="clock" class="w-full">{{ __('Record delay reason') }}</flux:button>
</flux:modal.trigger>

<flux:modal name="record-delay" class="w-full md:w-[30rem]">
    <form wire:submit="recordDelay" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Record delay reason') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Used in the delay analysis. The request stays where it is.') }}</flux:text>
        </div>
        <flux:select wire:model="delayCategory" :label="__('Category')" required>
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach (\App\Enums\DelayCategory::cases() as $category)
                <flux:select.option :value="$category->value">{{ $category->label() }} – {{ $category->description() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:textarea wire:model="delayReason" :label="__('Reason')" rows="2" required />
        <flux:textarea wire:model="delayCorrectiveAction" :label="__('Corrective action')" badge="Optional" rows="2" />
        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Back') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Record') }}</flux:button>
        </div>
    </form>
</flux:modal>
