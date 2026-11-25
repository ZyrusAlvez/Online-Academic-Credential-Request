<div class="space-y-6">
    <x-page-header title="Records" description="Every credential request." />

    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only :placeholder="__('Tracking no., name or student number')" clearable />
        <flux:select wire:model.live="status" :label="__('Status')" label:sr-only>
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="open">{{ __('All open') }}</flux:select.option>
            <flux:select.option value="needs_registrar">{{ __('Needs the Registrar') }}</flux:select.option>
            <flux:select.option value="waiting_for_requester">{{ __('Waiting for the requester') }}</flux:select.option>
            <flux:select.option value="processing">{{ __('Being processed') }}</flux:select.option>
            <flux:select.option value="overdue">{{ __('Request overdue (:count)', ['count' => $overdueCount]) }}</flux:select.option>
            <flux:select.option value="office_overdue">{{ __('Office overdue (:count)', ['count' => $officeOverdueCount]) }}</flux:select.option>
            @foreach ($statuses as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
            {{-- REQ-37-04: the Admin's deleted requests, restorable from their record. --}}
            @if ($isAdmin)
                <flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option>
            @endif
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
        <flux:select wire:model.live="evaluator" :label="__('Evaluator')" label:sr-only data-evaluator-filter>
            <flux:select.option value="">{{ __('All evaluators') }}</flux:select.option>
            @foreach ($evaluators as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Opened from a dashboard number: the requests behind it (REQ-34-17). --}}
    @if ($scopeLabel)
        <div class="flex flex-wrap items-center gap-2" data-dashboard-scope>
            <flux:badge color="blue" size="lg">
                {{ $scopeLabel }}
                <flux:badge.close wire:click="clearScope" :aria-label="__('Show every request')" class="cursor-pointer" />
            </flux:badge>
        </div>
    @endif

    <flux:table :paginate="$requests">
        <flux:table.columns>
            <flux:table.column>{{ __('Request') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Requester') }}</flux:table.column>
            <flux:table.column class="max-lg:hidden">{{ __('Currently at') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column class="max-sm:hidden">{{ __('Submitted') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($requests as $lead)
                {{-- One row per application; a line per credential (REQ-27-08). --}}
                @php($credentials = $lead->application->credentialRequests)
                @php($request = $credentials->first())
                @php($several = $credentials->count() > 1)
                <flux:table.row :key="$request->id">
                    <flux:table.cell class="whitespace-normal">
                        <a href="{{ route('records.show', $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $several ? $lead->application->reference_no : $request->reference_no }}</a>
                        <x-walk-in-badge :request="$request" class="ms-1" />
                        @foreach ($credentials as $credential)
                            <div class="text-xs text-zinc-600">{{ $credential->credentialType->name }}</div>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal">
                        <div class="text-ink">{{ $request->requester_name }}</div>
                        <div class="text-xs text-zinc-500">{{ $request->student_number }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-lg:hidden whitespace-normal text-zinc-700" data-whereabouts>
                        @foreach ($credentials as $credential)
                            <div>{{ $credential->staffWhereabouts() }}</div>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col items-start gap-1">
                            @foreach ($credentials as $credential)
                                <div class="flex flex-wrap gap-1">
                                    <x-request-status :status="$credential->status" />
                                    {{-- Worked out from the dates, not statuses; the office's first (REQ-30-10). --}}
                                    @if ($credential->openVisit?->isOverdue())
                                        <flux:badge size="sm" color="amber" icon="clock" data-office-overdue>{{ __('Office overdue') }}</flux:badge>
                                    @endif
                                    @if ($credential->isOverdue())
                                        <flux:badge size="sm" color="red" icon="clock" data-request-overdue>{{ __('Request overdue') }}</flux:badge>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="max-sm:hidden text-zinc-600">{{ $request->submitted_at?->format('M j, Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">{{ __('No requests match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
