<div class="space-y-6">
    <x-page-header title="Office Queue" :description="__('Documents sent to :office, oldest first. Accept receipt when a document physically arrives, then release it to the next office or return it for correction.', ['office' => $department?->name ?? __('your office')])" />

    @unless ($department)
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.text>{{ __('Your account is not assigned to an office yet. Please contact the Office of the Registrar.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    <div role="tablist" aria-label="{{ __('Queue') }}" class="flex flex-wrap gap-2">
        @foreach (['incoming' => __('Incoming (:n)', ['n' => $incomingCount]), 'in_office' => __('In our office (:n)', ['n' => $inOfficeCount]), 'history' => __('History')] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                @class([
                    'rounded-full px-4 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight',
                    'bg-primary text-white' => $tab === $key,
                    'bg-primary-50 text-primary hover:bg-primary-100' => $tab !== $key,
                ])>{{ $label }}</button>
        @endforeach
    </div>

    <div role="tabpanel">
        <x-visit-queue
            :visits="$visits"
            link-route="department.requests.show"
            :history="$tab === 'history'"
            :empty="match ($tab) { 'history' => __('Nothing completed yet.'), 'in_office' => __('No documents in your office.'), default => __('Nothing has been sent to your office.') }"
        />
    </div>
</div>
