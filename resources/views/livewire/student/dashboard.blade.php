<div class="space-y-6">
    <x-page-header title="My Requests" description="Request academic credentials and follow them from office to office.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('student.requests.create')" wire:navigate>{{ __('New request') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($requests->isEmpty())
        <div class="rounded-xl border border-dashed border-secondary p-10 text-center">
            <flux:icon.document-text class="mx-auto size-10 text-secondary" />
            <h2 class="mt-3 text-lg font-semibold text-primary">{{ __('No requests yet') }}</h2>
            <p class="mt-1 text-sm text-zinc-600">{{ __('Request your Transcript of Records or another credential to get started.') }}</p>
            <flux:button class="mt-5" variant="primary" icon="plus" :href="route('student.requests.create')" wire:navigate>{{ __('New request') }}</flux:button>
        </div>
    @else
        <ul class="grid gap-3">
            @foreach ($requests as $request)
                <li wire:key="request-{{ $request->id }}">
                    <a href="{{ route('student.requests.show', $request) }}" wire:navigate
                        class="flex flex-col gap-3 rounded-xl border border-secondary/50 bg-white p-4 transition hover:border-primary hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ $request->credentialType->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $request->reference_no }} · {{ __('Submitted :date', ['date' => $request->created_at->format('M j, Y')]) }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                            <span class="flex items-center gap-1 text-sm text-zinc-700">
                                <flux:icon.map-pin class="size-4 text-secondary" />
                                {{ $request->whereabouts() }}
                            </span>
                            <x-request-status :status="$request->status" />
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        {{ $requests->links() }}
    @endif
</div>
