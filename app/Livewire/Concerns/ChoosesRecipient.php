<?php

namespace App\Livewire\Concerns;

use App\Enums\RecipientType;
use App\Enums\ReviewStatus;
use App\Models\CredentialRequest;
use Illuminate\Validation\Rule;

/**
 * Who claimed the credential (REQ-16-20, 28): the owner, a verified representative of the
 * request, or a representative checked at the counter, whose name is typed in. Shared by the
 * Registrar's Release and the Admin's override (REQ-35-04), with
 * `partials/release-recipient.blade.php`.
 */
trait ChoosesRecipient
{
    /** The Release choice for a representative not on the request's list (REQ-16-28). */
    public const SOMEONE_ELSE = 'other';

    public string $recipientType = 'owner';

    public int|string|null $recipientRepresentativeId = null;

    /** A representative not declared on the request, checked at the counter (REQ-16-28). */
    public string $recipientName = '';

    /**
     * Validate the choice and return it as RequestWorkflow::release() takes it.
     *
     * @return array{recipient_type: RecipientType, representative_id: ?int, representative_name: ?string}
     */
    protected function chosenRecipient(CredentialRequest $request): array
    {
        // A verified representative is picked from the list; anyone else is typed in (REQ-16-28).
        $toRepresentative = $this->recipientType === RecipientType::Representative->value;
        $hasVerified = $request->representatives()->where('status', ReviewStatus::Verified)->exists();
        $atCounter = $toRepresentative && ($this->recipientRepresentativeId === self::SOMEONE_ELSE || (! $hasVerified && blank($this->recipientRepresentativeId)));

        $this->validate([
            'recipientType' => ['required', Rule::enum(RecipientType::class)],
            'recipientRepresentativeId' => [Rule::requiredIf($toRepresentative && $hasVerified), 'nullable', $atCounter ? 'string' : 'integer'],
            'recipientName' => [Rule::requiredIf($atCounter), 'nullable', 'string', 'max:150'],
        ], attributes: ['recipientRepresentativeId' => 'representative', 'recipientName' => "representative's full name"]);

        return [
            'recipient_type' => RecipientType::from($this->recipientType),
            'representative_id' => $toRepresentative && ! $atCounter ? (int) $this->recipientRepresentativeId : null,
            'representative_name' => $atCounter ? $this->recipientName : null,
        ];
    }
}
