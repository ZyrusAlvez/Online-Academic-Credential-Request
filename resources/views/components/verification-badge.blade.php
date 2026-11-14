@props(['verified' => false])

{{-- Shown only once a section of the requester's saved details is verified by the Office of the
     Registrar; unverified sections carry no badge (REQ-21-06). --}}
@if ($verified)
    <flux:badge size="sm" color="green" icon="check-badge" data-verification="verified">{{ __('Verified') }}</flux:badge>
@endif
