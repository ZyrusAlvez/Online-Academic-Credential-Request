<div class="mx-auto max-w-3xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('student.dashboard')" wire:navigate>{{ __('My Requests') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('New request') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-page-header title="Request a credential" description="Tell us what you need. You can follow your request from office to office after you submit it." />

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
                                    <flux:badge size="sm" color="amber">{{ $type->fee !== null ? __('Fee: ₱:fee', ['fee' => number_format((float) $type->fee, 2)]) : __('Requires payment') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green">{{ __('Free') }}</flux:badge>
                                @endif
                                <flux:badge size="sm" color="zinc">{{ trans_choice('~:count working day|~:count working days', $type->processing_days) }}</flux:badge>
                            </div>
                        </div>
                    </flux:radio>
                @endforeach
            </flux:radio.group>

            @if ($selectedType?->requires_payment)
                <flux:callout icon="credit-card" color="amber">
                    <flux:callout.heading>{{ __('This credential requires payment') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('After submitting, pay on the UPHSL online payment portal and upload your receipt on the request page.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="grid gap-6 sm:grid-cols-[8rem_1fr]">
                <flux:input wire:model="form.copies" type="number" min="1" max="10" :label="__('Copies')" required />
                <flux:input wire:model="form.purpose" :label="__('Purpose')" :placeholder="__('e.g. Employment, board exam, transfer')" required />
            </div>
        </section>

        <section aria-labelledby="who-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
            <div>
                <h2 id="who-heading" class="text-lg font-semibold text-primary">{{ __('2. Your academic details') }}</h2>
                <p class="mt-1 text-sm text-zinc-600">{{ __('Only your college will be asked to sign, not every college.') }}</p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.student_number" :label="__('Student number')" autocomplete="off" required />
                <flux:select wire:model="form.college_id" :label="__('College')" required>
                    <flux:select.option value="">{{ __('Choose your college…') }}</flux:select.option>
                    @foreach ($colleges as $college)
                        <flux:select.option :value="$college->id">{{ $college->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model="form.program" :label="__('Program / course')" :placeholder="__('e.g. BS Computer Science')" required />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:radio.group wire:model="form.student_status" :label="__('Status')">
                    @foreach (\App\Enums\StudentStatus::cases() as $status)
                        <flux:radio :value="$status->value" :label="$status->label()" />
                    @endforeach
                </flux:radio.group>
                <flux:input wire:model="form.last_school_year" :label="__('Last school year attended')" placeholder="2024-2025" inputmode="numeric" required />
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:button :href="route('student.dashboard')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Submit request') }}</flux:button>
        </div>
    </form>
</div>
