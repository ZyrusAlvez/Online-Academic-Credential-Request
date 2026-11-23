<?php

namespace App\Livewire\Concerns;

use App\Actions\Override\RecordOverride;
use App\Enums\OverrideStep;
use App\Enums\Role;
use App\Models\CredentialRequest;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * The Admin's Override on a request's record (spec 35): the credential's next step, recorded for
 * whoever couldn't, with when it happened and why. The step shown when the dialog opened is the
 * one recorded; if the request moved on meanwhile, it is refused (REQ-35-13). The using component
 * gives the credential shown through overrideTarget().
 */
trait OverridesSteps
{
    use ChoosesRecipient;
    use RunsTransitions;

    /** The step offered when the dialog opened (REQ-35-13). */
    #[Locked]
    public ?string $overrideStep = null;

    /** The latest history entry when the dialog opened (REQ-35-13). */
    #[Locked]
    public ?int $overrideSeenEventId = null;

    /** When the step happened, as the datetime-local field gives it (REQ-35-08). */
    public string $overrideAt = '';

    public string $overrideReason = '';

    /** A walk-in payment's official receipt number and the amount paid (REQ-35-03). */
    public string $overrideReceiptNo = '';

    public string $overrideAmount = '';

    abstract protected function overrideTarget(): CredentialRequest;

    public function openOverride(): void
    {
        $request = $this->overrideTarget();

        if (! $this->mayOverride($request)) {
            return;
        }

        $acting = $request->actingCredential();
        $this->resetValidation();
        $this->reset('overrideReason', 'overrideReceiptNo', 'overrideAmount', 'recipientType', 'recipientRepresentativeId', 'recipientName');
        $this->overrideStep = OverrideStep::for($acting)?->value;
        $this->overrideSeenEventId = $acting->latestEventId();
        $this->overrideAt = now()->format('Y-m-d\TH:i');

        Flux::modal('override')->show();
    }

    public function recordOverride(RecordOverride $action): void
    {
        $request = $this->overrideTarget();
        $step = OverrideStep::tryFrom((string) $this->overrideStep);

        if (! $this->mayOverride($request) || $step === null) {
            return;
        }

        $walkInPayment = $step === OverrideStep::ValidatePayment && $request->isWalkIn();

        $this->validate([
            // Exactly what the datetime-local field sends; no free-form dates (REQ-35-08).
            'overrideAt' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'overrideReason' => ['required', 'string', 'max:500'],
            ...($walkInPayment ? [
                'overrideReceiptNo' => ['required', 'string', 'max:50'],
                'overrideAmount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            ] : []),
        ], [
            'overrideAt.before_or_equal' => 'The time it happened can\'t be in the future.',
        ], [
            'overrideAt' => 'when it happened',
            'overrideReason' => 'reason',
            'overrideReceiptNo' => 'official receipt number',
            'overrideAmount' => 'amount paid',
        ]);

        $details = match (true) {
            $walkInPayment => ['receipt_no' => $this->overrideReceiptNo, 'amount' => (string) $this->overrideAmount],
            $step === OverrideStep::Release => $this->chosenRecipient($request),
            default => [],
        };

        $recorded = $this->attempt(fn () => $action->handle(
            $this->actor(),
            $request,
            $step,
            $this->overrideReason,
            Carbon::parse($this->overrideAt),
            $details,
            $this->overrideSeenEventId,
        ), 'Override recorded.');

        if ($recorded) {
            Flux::modal('override')->close();
            $this->reset('overrideStep', 'overrideSeenEventId', 'overrideReason', 'overrideReceiptNo', 'overrideAmount', 'recipientType', 'recipientRepresentativeId', 'recipientName');

            return;
        }

        // Refused: offer what the request is waiting for now, keeping what was typed (REQ-35-13).
        $acting = $this->overrideTarget()->actingCredential();
        $this->overrideStep = OverrideStep::for($acting)?->value;
        $this->overrideSeenEventId = $acting->latestEventId();

        if ($this->overrideStep === null) {
            Flux::modal('override')->close();
        }
    }

    /**
     * Only the Admin overrides (REQ-35-01): anyone else gets 403. For the Admin, a credential with
     * no step left to record is refused with a message instead (REQ-35-13).
     */
    private function mayOverride(CredentialRequest $request): bool
    {
        if (Gate::allows('override', $request)) {
            return true;
        }

        abort_unless($this->actor()->hasRole(Role::Admin), 403);
        $this->dispatch('notify', message: 'The request has moved on: there is no step to record now.');
        Flux::modal('override')->close();

        return false;
    }
}
