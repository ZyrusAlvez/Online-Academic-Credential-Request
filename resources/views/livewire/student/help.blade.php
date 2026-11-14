{{-- One help topic: its questions, then where to ask anything else (REQ-22-02..05). --}}
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header :title="__($help->label())" :description="__($help->description())" />

    <section aria-label="{{ __('Frequently asked questions') }}" class="rounded-xl border border-secondary/50 px-5 sm:px-6" data-help-topic="{{ $help->value }}">
        @include('help.'.$help->value)
    </section>

    <p class="flex items-start gap-2 text-sm text-zinc-600" data-help-contact>
        <flux:icon.chat-bubble-left-right variant="mini" class="mt-0.5 shrink-0 text-zinc-500" aria-hidden="true" />
        <span>{{ __('Still have a question? Ask the Office of the Registrar: they can help with anything not answered here.') }}</span>
    </p>
</div>
