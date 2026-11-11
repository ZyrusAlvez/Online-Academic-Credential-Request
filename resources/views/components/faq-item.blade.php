@props(['question'])

{{-- One frequently asked question, closed at first. A native <details>, so a click, Enter or Space
     opens it and screen readers announce it as expandable, with no script (REQ-22-02). --}}
<details {{ $attributes->class('group border-b border-zinc-200 last:border-b-0') }} data-faq-item>
    <summary class="flex cursor-pointer list-none items-start justify-between gap-4 py-4 text-start font-medium text-ink hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight [&::-webkit-details-marker]:hidden">
        <span>{{ $question }}</span>
        <flux:icon.chevron-down variant="mini" class="mt-0.5 shrink-0 text-zinc-500 transition-transform group-open:rotate-180" aria-hidden="true" />
    </summary>
    <div class="space-y-3 pb-5 text-sm leading-relaxed text-zinc-700 [&_a]:font-medium [&_a]:text-highlight [&_a]:underline [&_a]:underline-offset-2 [&_a:hover]:no-underline [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:ps-5 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-5">
        {{ $slot }}
    </div>
</details>
