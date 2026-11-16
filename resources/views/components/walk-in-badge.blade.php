@props(['request'])

{{-- A request recorded by the Registrar for a walk-in client (REQ-26-10). --}}
@if ($request->isWalkIn())
    <flux:badge size="sm" color="sky" icon="building-library" {{ $attributes }} data-walk-in-badge>{{ __('Walk-in') }}</flux:badge>
@endif
