@props(['status', 'customer' => false])

<flux:badge size="sm" :color="$customer && $status === \App\Enums\RequestStatus::ReturnedForCorrection ? \App\Enums\RequestStatus::InProgress->color() : $status->color()" {{ $attributes }}>{{ $customer ? $status->customerLabel() : $status->label() }}</flux:badge>
