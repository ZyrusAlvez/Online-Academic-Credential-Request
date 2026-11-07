{{-- Same column and sections as New Request (REQ-09-12). --}}
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="My Requests" description="Request academic credentials and follow them from office to office.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('student.requests.create')" wire:navigate>{{ __('New request') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <section aria-labelledby="requests-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h2 id="requests-heading" class="text-lg font-semibold text-primary">{{ __('Your requests') }}</h2>

        @if ($requests->isEmpty())
            <div class="py-6 text-center">
                <flux:icon.document-text class="mx-auto size-10 text-secondary" />
                <h3 class="mt-3 font-semibold text-ink">{{ __('No requests yet') }}</h3>
                <p class="mt-1 text-sm text-zinc-600">{{ __('Request your Transcript of Records or another credential to get started.') }}</p>
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
