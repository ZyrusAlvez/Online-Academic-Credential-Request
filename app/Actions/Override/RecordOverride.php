<?php

namespace App\Actions\Override;

use App\Enums\OverrideStep;
use App\Enums\RecipientType;
use App\Enums\Role;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\OfficeVisit;
use App\Models\User;
use App\Services\RequestWorkflow;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * The Admin records a credential's next step for whoever couldn't, with the reason and when it
 * happened (spec 35). The step runs the same workflow action the office or the Registrar would
 * (REQ-35-02..05), as an override (REQ-35-09, 10).
 */
class RecordOverride
{
    public function __construct(private readonly RequestWorkflow $workflow) {}

    /**
     * `$details` holds a walk-in payment's official receipt number and amount (REQ-35-03) and the
     * release's recipient (REQ-35-04). `$seenEventId` is the latest history entry the Admin saw
     * (`CredentialRequest::latestEventId()` of the acting credential); when given, the step is
     * refused if anything was recorded since (REQ-35-13).
     *
     * @param  array{receipt_no?: string, amount?: string, recipient_type?: RecipientType, representative_id?: ?int, representative_name?: ?string}  $details
     */
    public function handle(
        User $admin,
        CredentialRequest $request,
        OverrideStep $step,
        string $reason,
        CarbonInterface $happenedAt,
        array $details = [],
        ?int $seenEventId = null,
    ): void {
        if (! $admin->hasRole(Role::Admin)) {
            throw new AuthorizationException('Only the Admin records an override.');
        }

        // Read afresh, through the credential that carries the application until acceptance
        // (REQ-27-03): the page that asked may show an older state.
        $request = CredentialRequest::query()->findOrFail($request->getKey())->actingCredential();

        if (OverrideStep::for($request) !== $step || ($seenEventId !== null && $request->latestEventId() !== $seenEventId)) {
            throw new InvalidTransition('The request has moved on since the Override was opened.');
        }

        $workflow = $this->workflow->asOverride($reason, $this->when($request, $happenedAt));

        $run = match ($step) {
            OverrideStep::Receive => fn () => $workflow->acceptReceipt($this->visit($request), $admin),
            OverrideStep::Approve => fn () => $workflow->completeVisit($this->visit($request), $admin),
            OverrideStep::Forward => fn () => $workflow->forwardDocument($this->visit($request), $admin),
            OverrideStep::FinalCheck => fn () => $workflow->approveFinalCheck($request, $admin),
            OverrideStep::ValidatePayment => $request->isWalkIn()
                ? fn () => $workflow->verifyWalkInPayment($request, $admin, (string) ($details['receipt_no'] ?? ''), (string) ($details['amount'] ?? ''))
                : fn () => $workflow->verifyPaymentAndAccept($request, $admin),
            OverrideStep::Release => fn () => $workflow->release(
                $request,
                $admin,
                $details['recipient_type'] ?? RecipientType::Owner,
                $details['representative_id'] ?? null,
                representativeName: $details['representative_name'] ?? null,
            ),
            OverrideStep::Unlock => fn () => $workflow->unlockEvaluation($request, $admin),
        };

        $run();
    }

    /**
     * When the step happened: not in the future, and not before the minute of the request's latest
     * history entry; within that minute, the entry's own time, so the history stays in order
     * (REQ-35-08).
     */
    private function when(CredentialRequest $request, CarbonInterface $happenedAt): Carbon
    {
        $at = Carbon::instance($happenedAt)->setTimezone(now()->getTimezone());

        if ($at->isFuture()) {
            throw new InvalidTransition('The time it happened can\'t be in the future.');
        }

        /** @var ?Carbon $latest */
        $latest = $request->events()->reorder('created_at', 'desc')->value('created_at');

        if ($latest === null) {
            return $at;
        }

        if ($at->copy()->startOfMinute()->lt($latest->copy()->startOfMinute())) {
            throw new InvalidTransition('The time it happened can\'t be before the request\'s last entry, '.$latest->format('M j, Y g:i A').'.');
        }

        return $at->lt($latest) ? $latest->copy() : $at;
    }

    private function visit(CredentialRequest $request): OfficeVisit
    {
        return $request->openVisit()->with(['department', 'step', 'credentialRequest'])->firstOrFail();
    }
}
