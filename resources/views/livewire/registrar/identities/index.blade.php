<div class="space-y-6">
    <x-page-header title="Identity Checks" description="Requesters' valid IDs. Verify that each account belongs to the document owner before their requests are accepted." />

    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" label:sr-only placeholder="Search name, email or student number" clearable />
        <flux:select wire:model.live="status" :label="__('Identity status')" label:sr-only>
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($statuses as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$requesters">
        <flux:table.columns>
            <flux:table.column>{{ __('Requester') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Academic information') }}</flux:table.column>
            <flux:table.column>{{ __('Valid ID') }}</flux:table.column>
            <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($requesters as $requester)
                @php($document = $requester->latestIdentityDocument)
                <flux:table.row :key="$requester->id">
                    <flux:table.cell class="whitespace-normal">
                        <div class="font-medium text-ink">{{ $requester->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $requester->email }}</div>
                        @if ($requester->contact_number)
                            <div class="text-xs text-zinc-500">{{ $requester->contact_number }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal text-sm text-zinc-700">
                        @if ($requester->academic_status)
                            <div>{{ $requester->academic_status->label() }} · {{ $requester->college?->name }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $requester->program }} · {{ $requester->attended_from }}–{{ $requester->attended_to }}
                                @if ($requester->student_number) · {{ $requester->student_number }} @endif
                            </div>
                        @else
                            <span class="text-zinc-500">{{ __('Not filled in yet') }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        <flux:badge size="sm" :color="$requester->identity_status->color()">{{ $requester->identity_status->label() }}</flux:badge>
                        @if ($document)
                            <div class="mt-1 text-xs">
                                <a href="{{ route('identity-documents.show', $document) }}" target="_blank" rel="noopener" class="text-highlight underline underline-offset-2 hover:no-underline">
                                    {{ $document->id_type }}<span class="sr-only"> ({{ __('opens in a new tab') }})</span>
                                </a>
                                <span class="text-zinc-500">· {{ $document->created_at->format('M j, Y') }}</span>
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($document && $document->status === \App\Enums\ReviewStatus::Pending)
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="primary" icon="check" wire:click="verify({{ $document->id }})" :wire:confirm="__('Verify the identity of :name?', ['name' => $requester->name])">{{ __('Verify') }}</flux:button>
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="confirmReject({{ $document->id }})">{{ __('Reject') }}</flux:button>
                            </div>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">{{ __('No requesters match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="reject-id" class="w-full md:w-[32rem]">
        <form wire:submit="reject" class="space-y-6">
            <flux:heading size="lg">{{ __('Reject valid ID') }}</flux:heading>
            <flux:textarea wire:model="remarks" :label="__('Reason')" :description="__('Sent to the requester, who can then upload another ID.')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Reject ID') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
