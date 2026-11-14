{{-- The Data Privacy notice and consent at the end of a summary (REQ-15-05, 07).
     Expects: $prefix (the RequesterProfileForm property); optional $level (heading level, 3 by default). --}}
@php($tag = ($level ?? 3) === 2 ? 'h2' : 'h3')
<section aria-labelledby="privacy-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
    <{{ $tag }} id="privacy-heading" class="text-lg font-semibold text-primary">{{ __('Data Privacy') }}</{{ $tag }}>
    <p class="text-sm">{{ __('The University of Perpetual Help System uses your details, academic information and the valid ID you upload only to process your credential requests, in accordance with the Data Privacy Act of 2012 (RA 10173).') }}</p>

    {{-- Ticked every time: nothing is saved or submitted without it (REQ-15-05, 07). --}}
    <flux:checkbox wire:model="{{ $prefix }}.privacy_consent" :label="__('I agree to the processing of my personal data')" data-privacy-consent />
</section>
