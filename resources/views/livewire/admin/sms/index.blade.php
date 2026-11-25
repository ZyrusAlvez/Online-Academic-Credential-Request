<div class="space-y-6" wire:init="loadBalance">
    <x-page-header title="SMS" :description="__('[ACTION REQUIRED] notices texted to requesters with a Philippine number, through IPROG SMS.')" />

    {{-- REQ-36-12: off until the Admin turns it on. --}}
    <section aria-labelledby="sms-switch-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="sms-switch-heading" class="text-lg font-semibold text-primary">{{ __('SMS notifications') }}</h2>
        <flux:switch wire:model.live="enabled" :label="__('Text requesters')" data-sms-switch />

        @if ($logsOnly)
            <flux:callout icon="information-circle" color="zinc" data-sms-local-note>
                <flux:callout.text>{{ __('This is a development copy: texts are only written to the log.') }}</flux:callout.text>
            </flux:callout>
        @endif
    </section>

    {{-- REQ-36-13: fetched from IPROG after the page loads. --}}
    <section aria-labelledby="sms-balance-heading" class="space-y-3 rounded-xl border border-secondary/50 p-5 sm:p-6" data-sms-balance>
        <div class="flex items-center justify-between gap-3">
            <h2 id="sms-balance-heading" class="text-lg font-semibold text-primary">{{ __('Credits left') }}</h2>
            <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="loadBalance" wire:loading.attr="disabled" wire:target="loadBalance">{{ __('Refresh') }}</flux:button>
        </div>

        <div wire:loading wire:target="loadBalance" class="text-sm text-zinc-500">{{ __('Checking IPROG SMS…') }}</div>
        <div wire:loading.remove wire:target="loadBalance">
            @switch($balanceProblem)
                @case(null)
                    <p class="text-3xl font-semibold tabular-nums text-ink" data-sms-credits>{{ rtrim(rtrim(number_format((float) $balance, 2), '0'), '.') }}</p>
                    @break
                @case('no_key')
                    <p class="text-sm text-zinc-600" data-sms-no-key>{{ __('The IPROG SMS key is not set on this server (IPROG_API_TOKEN).') }}</p>
                    @break
                @case('unreachable')
                    <p class="text-sm text-red-700" data-sms-unreachable>{{ __('IPROG SMS could not be reached or refused the key. Try again later.') }}</p>
                    @break
                @default
                    <p class="text-sm text-zinc-500">{{ __('Checking IPROG SMS…') }}</p>
            @endswitch
        </div>
    </section>
</div>
