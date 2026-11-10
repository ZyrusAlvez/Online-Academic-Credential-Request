{{-- Same column and sections as New Request (REQ-09-12). --}}
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="My Requests">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('student.requests.create')" wire:navigate>{{ __('New request') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <section aria-label="{{ __('Your requests') }}" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
        {{-- Credential name or tracking number (REQ-09-20); nothing to search before a first request. --}}
        @if ($searching || $requests->isNotEmpty())
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search your requests')" label:sr-only
                :placeholder="__('Search by credential or tracking no.')" clearable data-request-search />
        @endif

        @if ($requests->isEmpty() && $searching)
            <div class="py-6 text-center" role="status">
                <flux:icon.magnifying-glass class="mx-auto size-10 text-secondary" />
                <h3 class="mt-3 font-semibold text-ink">{{ __('No Matching Requests') }}</h3>
                <p class="mt-1 text-sm text-zinc-600">{{ __('No request has a credential or tracking number containing “:term”.', ['term' => trim($search)]) }}</p>
                <flux:button class="mt-5" variant="ghost" icon="x-mark" wire:click="clearSearch">{{ __('Clear search') }}</flux:button>
            </div>
        @elseif ($requests->isEmpty())
            <div class="py-6 text-center">
                <flux:icon.document-text class="mx-auto size-10 text-secondary" />
                <h3 class="mt-3 font-semibold text-ink">{{ __('No requests yet') }}</h3>
                <flux:button class="mt-5" variant="primary" icon="plus" :href="route('student.requests.create')" wire:navigate>{{ __('New request') }}</flux:button>
            </div>
        @else
            <ul role="list" class="-mx-2 divide-y divide-secondary/30">
                @foreach ($requests as $request)
                    <li wire:key="request-{{ $request->id }}" class="py-1">
                        <a href="{{ route('student.requests.show', $request) }}" wire:navigate
                            class="flex flex-col gap-3 rounded-lg px-2 py-3 transition hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $request->credentialType->name }}</p>
                                <p class="text-xs text-zinc-600">{{ $request->reference_no }} · {{ __('Submitted :date', ['date' => $request->submitted_at?->format('M j, Y')]) }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                                <span class="flex items-center gap-1 text-sm text-zinc-700">
                                    <flux:icon.map-pin class="size-4 text-secondary" />
                                    {{ $request->whereabouts() }}
                                </span>
                                <x-request-status :status="$request->status" customer />
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{ $requests->links() }}
        @endif
    </section>
</div>
