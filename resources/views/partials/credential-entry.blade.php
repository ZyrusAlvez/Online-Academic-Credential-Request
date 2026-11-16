{{-- One credential of section 1: the credential and its details card, the purpose and the receiving
     school. Expects: $path (the Livewire path: `form` for the first, `form.more.N` for the others),
     $entry (its current values), $index (0 for the first), $types, $routes, $purposes. --}}
@php($selectedType = $types->firstWhere('id', (int) ($entry['credential_type_id'] ?? 0)))
@php($chosenJs = '$wire.get('.\Illuminate\Support\Js::from($path.'.credential_type_id').')')

<x-select-menu wire:model.live="{{ $path }}.credential_type_id" :label="__('Credential')"
    :options="$types->pluck('name', 'id')->all()" :value="$entry['credential_type_id'] ?? null"
    :placeholder="__('Choose a credential…')" :search-label="__('Search credentials')" />

{{-- Every credential's details are on the page and the chosen one is shown in the browser at
     once, without a trip to the server (REQ-15-15). --}}
<div aria-live="polite">
@foreach ($types as $type)
    {{-- The chosen credential's details (REQ-04-02), laid out as a card (REQ-09-11). --}}
    <div wire:key="credential-card-{{ $index }}-{{ $type->id }}" data-credential-card="{{ $type->id }}"
        x-show="String({{ $chosenJs }} ?? '') === '{{ $type->id }}'" @if ((int) ($entry['credential_type_id'] ?? 0) !== $type->id) x-cloak style="display: none" @endif
        class="overflow-hidden rounded-xl border border-secondary/50 bg-white text-sm">
        <div class="space-y-1 border-b border-secondary/40 bg-primary-50 px-4 py-3 sm:px-5">
            <h3 class="text-base font-semibold text-primary">{{ $type->name }}</h3>
            @if ($type->description)
                <p class="text-ink">{{ $type->description }}</p>
            @endif
        </div>

        <div class="space-y-6 p-4 sm:p-5">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3">
                    <flux:icon.banknotes class="size-5 shrink-0 text-primary" />
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-600">{{ __('Fee') }}</dt>
                        @if ($type->requires_payment && $type->fee !== null)
                            <dd class="mt-0.5 font-semibold text-ink">₱{{ number_format((float) $type->fee, 2) }}</dd>
                        @else
                            <dd class="mt-0.5 font-semibold text-ink">{{ $type->requires_payment ? __('Requires payment') : __('Free') }}</dd>
                        @endif
                    </div>
                </div>
                <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3">
                    <flux:icon.clock class="size-5 shrink-0 text-primary" />
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-600">{{ __('Processing period') }}</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ trans_choice(':count working day|:count working days', $type->processing_days) }}</dd>
                        <dd class="text-zinc-600">{{ __('from acceptance') }}</dd>
                    </div>
                </div>
            </dl>

            <div>
                <h4 class="font-semibold text-ink">{{ __('Offices that process it') }}</h4>
                <ol role="list" class="mt-3">
                    @foreach ($routes[$type->id] as $stop)
                        <li class="relative flex gap-3 pb-4 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute top-7 bottom-1 left-3 w-px -translate-x-1/2 bg-secondary" aria-hidden="true"></span>
                            @endunless
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                            <span class="pt-0.5 text-ink"><span class="font-medium">{{ $stop['office'] }}</span> <span class="text-zinc-600">({{ strtolower($stop['task']) }})</span></span>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($type->special_conditions)
                <div class="flex items-start gap-2 rounded-lg bg-primary-50 p-3 text-ink">
                    <flux:icon.information-circle variant="mini" class="mt-0.5 shrink-0 text-primary" />
                    <p><span class="font-semibold">{{ __('Special conditions:') }}</span> {{ $type->special_conditions }}</p>
                </div>
            @endif
        </div>
    </div>
@endforeach
</div>

<div class="space-y-3">
    <flux:select wire:model.live="{{ $path }}.purpose" :label="__('Purpose')" required>
        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
        @foreach ($purposes as $purpose)
            <flux:select.option :value="$purpose">{{ $purpose }}</flux:select.option>
        @endforeach
        <flux:select.option :value="\App\Livewire\Forms\CredentialRequestForm::OTHER_PURPOSE">{{ __('Others (please specify)') }}</flux:select.option>
    </flux:select>
    @if (($entry['purpose'] ?? '') === \App\Livewire\Forms\CredentialRequestForm::OTHER_PURPOSE)
        <flux:input wire:model.live.blur="{{ $path }}.purpose_other" :label="__('Please specify')" :placeholder="__('Type the purpose of your request')" maxlength="500" required />
    @endif
</div>

{{-- One field whose wording follows the chosen credential in the browser (REQ-15-15). --}}
@php($transferIds = $types->where('requires_receiving_institution', true)->pluck('id')->map(fn ($id) => (string) $id)->values())
<div data-receiving-school x-data="{ get transfer() { return @js($transferIds).includes(String({{ $chosenJs }} ?? '')) } }">
    <flux:field>
        <flux:label>
            <span x-text="transfer ? @js(__('Receiving school')) : @js(__('Receiving school, employer or agency'))">{{ $selectedType?->requires_receiving_institution ? __('Receiving school') : __('Receiving school, employer or agency') }}</span>
            {{-- Flux's own "Optional" badge, shown only when the field is optional. --}}
            <span x-show="! transfer" @if ($selectedType?->requires_receiving_institution) style="display: none" @endif class="ms-1.5 -my-1 rounded-[4px] bg-zinc-800/5 px-1.5 py-1 text-xs text-zinc-800/70" aria-hidden="true">{{ __('Optional') }}</span>
        </flux:label>
        <flux:description>
            <span x-text="transfer ? @js(__('Its complete name.')) : @js(__('If the credential is for a specific institution.'))">{{ $selectedType?->requires_receiving_institution ? __('Its complete name.') : __('If the credential is for a specific institution.') }}</span>
        </flux:description>
        <flux:input wire:model.live.blur="{{ $path }}.receiving_institution" x-bind:required="transfer" />
        <flux:error name="{{ $path }}.receiving_institution" />
    </flux:field>
</div>
