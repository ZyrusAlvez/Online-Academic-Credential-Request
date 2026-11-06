{{-- The summary of the requester's details, the ID that will be used and the Data Privacy notice
     (REQ-15-05, REQ-15-07). Expects: $prefix, $fields (the RequesterProfileForm), $summary,
     $savedId, $user. --}}
@foreach ($summary as $heading => $rows)
    <section aria-labelledby="summary-heading-{{ $loop->index }}" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
        <h3 id="summary-heading-{{ $loop->index }}" class="text-lg font-semibold text-primary">{{ __($heading) }}</h3>
        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            @foreach ($rows as $label => $value)
                <div>
                    <dt class="text-zinc-500">{{ __($label) }}</dt>
                    <dd class="font-medium break-words text-ink">{{ __($value) }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
@endforeach

<section aria-labelledby="summary-id-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
    <h3 id="summary-id-heading" class="text-lg font-semibold text-primary">{{ __('Valid ID') }}</h3>

    @if ($fields->id_file)
        {{-- A signed, short-lived preview of the chosen file; it is only stored on the final save. --}}
        @php($previewUrl = $fields->id_file->temporaryUrl())
        @php($previewName = $fields->id_file->getClientOriginalName())
        @php($previewType = (string) $fields->id_file->getMimeType())
        <dl class="space-y-3 text-sm" data-id-summary-preview>
            <div>
                <dt class="text-zinc-500">{{ __('New upload') }}</dt>
                <dd class="font-medium break-all text-ink">{{ $fields->id_type }}: {{ $previewName }}</dd>
                <dd class="mt-3">
                    @if (str_starts_with($previewType, 'image/'))
                        <x-file-link :href="$previewUrl" :download="$previewUrl" :name="$previewName" :type="$previewType" class="inline-block no-underline">
                            <img src="{{ $previewUrl }}" alt="{{ __('Your chosen ID (open larger)') }}" class="max-h-64 max-w-full rounded-lg border border-secondary/50 object-contain">
                        </x-file-link>
                    @else
                        <x-file-link :href="$previewUrl" :download="$previewUrl" :name="$previewName" :type="$previewType" class="font-medium">{{ __('View PDF') }}</x-file-link>
                    @endif
                </dd>
            </div>
        </dl>
    @elseif ($savedId)
        <x-identity-document-list :documents="collect([$savedId])" />
    @endif
</section>

<section aria-labelledby="privacy-heading" class="space-y-4 rounded-xl border border-secondary/50 p-5 sm:p-6">
    <h3 id="privacy-heading" class="text-lg font-semibold text-primary">{{ __('Data privacy') }}</h3>
    <p class="text-sm">{{ __('The University of Perpetual Help System uses your details, academic information and the valid ID you upload only to process your credential requests, in accordance with the Data Privacy Act of 2012 (RA 10173).') }}</p>

    @if ($user->privacy_consented_at)
        <flux:text size="sm">{{ __('You agreed to the processing of your personal data on :date.', ['date' => $user->privacy_consented_at->format('M j, Y')]) }}</flux:text>
    @else
        <flux:checkbox wire:model="{{ $prefix }}.privacy_consent" :label="__('I agree to the processing of my personal data')" />
    @endif
</section>
