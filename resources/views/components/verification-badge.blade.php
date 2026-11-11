@props(['verified' => false])

{{-- Whether a section of the requester's saved details is verified by the Office of the Registrar
     or still for verification (REQ-21-06). --}}
@if ($verified)
    <flux:badge size="sm" color="green" icon="check-badge" data-verification="verified">{{ __('Verified') }}</flux:badge>
@else
    <flux:badge size="sm" color="amber" icon="clock" data-verification="pending">{{ __('For verification') }}</flux:badge>
@endif
