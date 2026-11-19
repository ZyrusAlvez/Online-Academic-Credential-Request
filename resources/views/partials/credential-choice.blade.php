{{-- Section 1, "What Do You Need?": one or more credentials, each with its details card, purpose and
     receiving school (REQ-27-10). On New Request and the Registrar's Walk-in Request (REQ-26-02);
     Edit Request passes several: false and keeps the first only.
     Expects: $form (the CredentialRequestForm, as `form`), $types, $routes, $purposes; optionally
     $room, how many credentials the requester may still request (REQ-33-04). --}}
@php($several ??= true)
@php($room = min($room ?? \App\Livewire\Forms\CredentialRequestForm::MAX_CREDENTIALS, \App\Livewire\Forms\CredentialRequestForm::MAX_CREDENTIALS))
<section aria-labelledby="what-heading" class="space-y-6 rounded-xl border border-secondary/50 p-5 sm:p-6">
    <h2 id="what-heading" class="text-lg font-semibold text-primary">{{ __('1. What Do You Need?') }}</h2>

    @include('partials.credential-entry', [
        'path' => 'form', 'index' => 0,
        'entry' => ['credential_type_id' => $form->credential_type_id, 'purpose' => $form->purpose],
    ])

    @if ($several)
        @foreach ($form->more as $i => $entry)
            <div wire:key="credential-entry-{{ $i }}" class="space-y-6 border-t border-secondary/50 pt-6" data-credential-entry="{{ $i + 2 }}">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-primary">{{ __('Credential :n', ['n' => $i + 2]) }}</h3>
                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-700!" wire:click="removeCredential({{ $i }})" data-remove-credential>{{ __('Remove') }}</flux:button>
                </div>
                @include('partials.credential-entry', ['path' => "form.more.{$i}", 'index' => $i + 1, 'entry' => $entry])
            </div>
        @endforeach

        @if (count($form->more) + 1 < $room)
            <flux:button icon="plus" wire:click="addCredential" data-add-credential>{{ __('Add another credential') }}</flux:button>
        @endif
    @endif
</section>
