<div class="space-y-8">
    <x-page-header title="Registrar Dashboard" description="Overview of all credential requests." />

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('registrar.requests', ['status' => 'review']) }}" wire:navigate class="rounded-xl border border-secondary/50 p-5 hover:border-primary focus-visible:outline-2 focus-visible:outline-highlight">
            <p class="text-sm text-zinc-600">{{ __('Needs your review') }}</p>
            <p class="mt-1 text-3xl font-bold text-primary">{{ $reviewCount }}</p>
        </a>
        <a href="{{ route('registrar.requests', ['status' => 'ready_for_release']) }}" wire:navigate class="rounded-xl border border-secondary/50 p-5 hover:border-primary focus-visible:outline-2 focus-visible:outline-highlight">
            <p class="text-sm text-zinc-600">{{ __('Ready for release') }}</p>
            <p class="mt-1 text-3xl font-bold text-primary">{{ $readyCount }}</p>
        </a>
    </div>

    <section aria-labelledby="office-queue-heading" class="space-y-3">
        <h2 id="office-queue-heading" class="text-lg font-semibold text-primary">{{ __('Office of the Registrar queue') }}</h2>
        <p class="text-sm text-zinc-600">{{ __('Requests whose current step is the final records verification by your office.') }}</p>
        <x-step-queue :steps="$officeQueue" link-route="registrar.requests.show" :empty="__('Nothing waiting for the Office of the Registrar.')" />
    </section>
</div>
