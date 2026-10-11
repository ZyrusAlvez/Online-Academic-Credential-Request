<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Notifications" :description="trans_choice(':count unread notification|:count unread notifications', $unread)">
        <x-slot:actions>
            @if ($unread > 0)
                <flux:button wire:click="markAllRead" icon="check">{{ __('Mark all read') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($notifications->isEmpty())
        <p class="rounded-xl border border-dashed border-secondary p-10 text-center text-zinc-600">{{ __('You have no notifications yet.') }}</p>
    @else
        <ul class="divide-y divide-secondary/40 rounded-xl border border-secondary/50">
            @foreach ($notifications as $notification)
                <li wire:key="n-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')"
                        @class(['flex w-full gap-3 p-4 text-left hover:bg-primary-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-highlight', 'bg-primary-50/60' => ! $notification->read_at])>
                        <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-highlight' => ! $notification->read_at, 'bg-zinc-300' => $notification->read_at]) aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span @class(['block text-ink', 'font-semibold' => ! $notification->read_at])>{{ $notification->data['title'] ?? '' }}</span>
                            <span class="block text-sm text-zinc-700">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="mt-1 block text-xs text-zinc-500">{{ $notification->data['reference_no'] ?? '' }} · {{ $notification->created_at->format('M j, Y g:i A') }}</span>
                            @if (! $notification->read_at)<span class="sr-only">{{ __('(unread)') }}</span>@endif
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>

        {{ $notifications->links() }}
    @endif
</div>
