<div class="space-y-6">
    <x-page-header title="Requests" description="Review new requests and receipts, and follow every request through the offices.">
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" :href="$exportUrl">{{ __('Export CSV') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_auto_auto_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only :placeholder="__('Reference, student name or number')" clearable />
        <flux:select wire:model.live="status" :label="__('Status')" label:sr-only>
            <flux:select.option value="review">{{ __('Needs review (:count)', ['count' => $reviewCount]) }}</flux:select.option>
            <flux:select.option value="overdue">{{ __('Overdue (:count)', ['count' => $overdueCount]) }}</flux:select.option>
            <flux:select.option value="open">{{ __('All open') }}</flux:select.option>
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($statuses as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="type" :label="__('Credential')" label:sr-only>
            <flux:select.option value="">{{ __('All credentials') }}</flux:select.option>
            @foreach ($types as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="office" :label="__('Currently at')" label:sr-only>
            <flux:select.option value="">{{ __('Any office') }}</flux:select.option>
            @foreach ($offices as $option)
                <flux:select.option :value="$option->id">{{ __('At :office', ['office' => $option->name]) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$requests">
        <flux:table.columns>
            <flux:table.column>{{ __('Request') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Student') }}</flux:table.column>
            <flux:table.column class="max-lg:hidden">{{ __('Currently at') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Submitted') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($requests as $request)
                <flux:table.row :key="$request->id">
                    <flux:table.cell class="whitespace-normal">
                        <a href="{{ route('registrar.requests.show', $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                        <div class="text-xs text-zinc-600">{{ $request->credentialType->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal">
                        <div class="text-ink">{{ $request->student->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $request->student_number }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-lg:hidden whitespace-normal text-zinc-700">{{ $request->whereabouts() }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            <x-request-status :status="$request->status" />
                            @if ($request->isOverdue())
                                <flux:badge size="sm" color="red" icon="clock">{{ __('Overdue') }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="max-sm:hidden text-zinc-600">{{ $request->created_at->format('M j, Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">{{ __('No requests match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
