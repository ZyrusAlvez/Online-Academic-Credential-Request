<div class="space-y-6">
    <x-page-header title="Signature Queue" :description="__('Requests waiting for the signature of :office, oldest first.', ['office' => $department?->name ?? __('your office')])" />

    @unless ($department)
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.text>{{ __('Your account is not assigned to a department yet. Please contact the Office of the Registrar.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    <div role="tablist" aria-label="{{ __('Queue') }}" class="flex flex-wrap gap-2">
        @foreach (['pending' => __('For signature (:n)', ['n' => $pendingCount]), 'on_hold' => __('On hold (:n)', ['n' => $onHoldCount]), 'history' => __('History')] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class([
                    'rounded-full px-4 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight',
                    'bg-primary text-white' => $tab === $key,
                    'bg-primary-50 text-primary hover:bg-primary-100' => $tab !== $key,
                ])>{{ $label }}</button>
        @endforeach
    </div>

    <div role="tabpanel">
        <x-step-queue
            :steps="$steps"
            link-route="department.requests.show"
            :history="$tab === 'history'"
            :empty="match ($tab) { 'history' => __('Nothing signed yet.'), 'on_hold' => __('No requests on hold.'), default => __('Nothing is waiting for your signature.') }"
        />
    </div>
</div>
