{{-- What needs attention before the summary (REQ-01-26). Focus moves to the first marked field on close. --}}
<flux:modal name="profile-errors" class="w-full md:w-[32rem]" x-on:close="document.querySelector('form [data-invalid]')?.focus()">
    <div class="space-y-5">
        <flux:heading size="lg">{{ __('Some details need your attention') }}</flux:heading>
        <flux:text>{{ __('Complete or correct the following. The fields are marked in red on the form.') }}</flux:text>
        <ul class="list-disc space-y-1 pl-5 text-sm text-ink">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="primary">{{ __('OK') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
