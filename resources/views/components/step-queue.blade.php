@props(['steps', 'linkRoute', 'empty' => 'Nothing waiting.', 'history' => false])

<flux:table :paginate="$steps instanceof \Illuminate\Contracts\Pagination\Paginator ? $steps : null">
    <flux:table.columns>
        <flux:table.column>{{ __('Request') }}</flux:table.column>
        <flux:table.column class="max-md:hidden">{{ __('Student') }}</flux:table.column>
        @unless ($history)
            <flux:table.column class="max-lg:hidden">{{ __('College') }}</flux:table.column>
        @endunless
        <flux:table.column>{{ $history ? __('Completed') : __('Waiting') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @forelse ($steps as $step)
            @php($request = $step->credentialRequest)
            <flux:table.row :key="$step->id">
                <flux:table.cell class="whitespace-normal">
                    <a href="{{ route($linkRoute, $request) }}" wire:navigate class="font-medium text-highlight underline underline-offset-2 hover:no-underline">{{ $request->reference_no }}</a>
                    <div class="text-xs text-zinc-600">{{ $request->credentialType->name }}</div>
                    @if ($step->status === \App\Enums\StepStatus::OnHold && $step->remarks)
                        <div class="mt-1 text-xs text-amber-800">{{ __('On hold: :reason', ['reason' => $step->remarks]) }}</div>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="max-md:hidden whitespace-normal">{{ $request->student->name }}</flux:table.cell>
                @unless ($history)
                    <flux:table.cell class="max-lg:hidden">{{ $request->college->code }}</flux:table.cell>
                @endunless
                <flux:table.cell class="text-zinc-700">
                    @if ($history)
                        <flux:badge size="sm" :color="$step->status->color()">{{ $step->status->label() }}</flux:badge>
                        <div class="text-xs text-zinc-600">{{ $step->acted_at?->format('M j, Y g:i A') }}@if ($step->actor) · {{ $step->actor->name }}@endif</div>
                    @else
                        <time datetime="{{ $step->updated_at->toIso8601String() }}" title="{{ $step->updated_at->format('M j, Y g:i A') }}">{{ $step->updated_at->diffForHumans(short: true) }}</time>
                    @endif
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">{{ $empty }}</flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>
