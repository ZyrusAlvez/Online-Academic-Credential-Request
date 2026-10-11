<div wire:poll.30s>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" class="relative" :aria-label="trans_choice('Notifications, :count unread|Notifications, :count unread', $unread)">
            <flux:icon.bell class="size-5 text-primary" />
            @if ($unread > 0)
                <span class="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-red-700 px-1 text-[0.7rem] font-bold text-white" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
            @endif
        </flux:button>

        <flux:menu class="w-[min(22rem,calc(100vw-2rem))]">
            <div class="flex items-center justify-between px-2 py-1.5">
                <span class="text-sm font-semibold text-primary">{{ __('Notifications') }}</span>
                @if ($unread > 0)
                    <button type="button" wire:click="markAllRead" class="text-xs text-highlight underline underline-offset-2">{{ __('Mark all read') }}</button>
                @endif
            </div>
            <flux:menu.separator />

            @forelse ($latest as $notification)
                <flux:menu.item wire:click="open('{{ $notification->id }}')" class="h-auto! items-start! py-2! whitespace-normal">
                    <div class="flex gap-2">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-highlight' => ! $notification->read_at, 'bg-transparent' => $notification->read_at]) aria-hidden="true"></span>
                        <div class="min-w-0">
                            <p @class(['text-sm text-ink', 'font-semibold' => ! $notification->read_at])>{{ $notification->data['title'] ?? '' }}</p>
                            <p class="text-xs text-zinc-600">{{ $notification->data['reference_no'] ?? '' }} · {{ $notification->created_at->diffForHumans() }}</p>
                            @if (! $notification->read_at)<span class="sr-only">{{ __('(unread)') }}</span>@endif
                        </div>
                    </div>
                </flux:menu.item>
            @empty
                <p class="px-2 py-4 text-center text-sm text-zinc-500">{{ __('You have no notifications yet.') }}</p>
            @endforelse

            <flux:menu.separator />
            <flux:menu.item icon="inbox" :href="route('notifications')" wire:navigate>{{ __('View all') }}</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
