{{--
    Who claimed the credential, for a component using ChoosesRecipient: the owner, or a
    representative, a verified one from the list or someone else typed in and checked at the
    counter (REQ-16-20, 28). Requires $request and $verifiedRepresentatives.
--}}
<flux:radio.group wire:model.live="recipientType" :label="__('Claimed by')">
    <flux:radio value="owner" :label="__('The document owner: :name', ['name' => $request->requester_name])" />
    <flux:radio value="representative" :label="__('A representative')" />
</flux:radio.group>
@if ($recipientType === 'representative')
    @if ($verifiedRepresentatives->isNotEmpty())
        <flux:select wire:model.live="recipientRepresentativeId" :label="__('Representative')" required>
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach ($verifiedRepresentatives as $representative)
                <flux:select.option :value="$representative->id">{{ $representative->full_name }}</flux:select.option>
            @endforeach
            <flux:select.option :value="$this::SOMEONE_ELSE">{{ __('Someone else') }}</flux:select.option>
        </flux:select>
    @endif
    @if ($verifiedRepresentatives->isEmpty() || $recipientRepresentativeId === $this::SOMEONE_ELSE)
        <flux:input wire:model="recipientName" :label="__('Representative\'s full name')" maxlength="150" autocomplete="off" required data-counter-representative />
    @endif
    <p class="flex gap-2 rounded-lg border border-amber-500 bg-amber-50 p-3 text-sm text-amber-950" data-release-reminder>
        <flux:icon.exclamation-triangle variant="mini" class="shrink-0 text-amber-600" />
        <span>{{ __('Confirm the SPA and the document owner\'s valid ID, and the representative\'s valid ID.') }}</span>
    </p>
@endif
