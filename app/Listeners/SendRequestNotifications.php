<?php

namespace App\Listeners;

use App\Enums\RequestEventType;
use App\Enums\Role;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Events\RequestEventRecorded;
use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Support\OfficeStaff;
use App\Support\RequestRegistrars;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about a ledger event (spec 06): the requester, the office a document was
 * just forwarded or returned to, and registrars (cashiers for a proof of payment) when something
 * needs them: every registrar for a new request, otherwise its evaluator (REQ-06-21).
 */
class SendRequestNotifications
{
    /** Events that need a registrar's attention. */
    private const FOR_REGISTRARS = [
        RequestEventType::Submitted,
        RequestEventType::PaymentSubmitted,
        RequestEventType::Resubmitted,
        RequestEventType::CustomerResponded,
        RequestEventType::RepresentativeDeclared,
        RequestEventType::IdentityResubmitted,
    ];

    /** An office finished with the paper, which then waits in Outgoing for the Registrar (REQ-32-11). */
    private const FOR_OUTGOING = [
        RequestEventType::StepCompleted,
        RequestEventType::DocumentReturned,
        RequestEventType::Corrected,
    ];

    public function handle(RequestEventRecorded $recorded): void
    {
        $event = $recorded->event->loadMissing(['department', 'step', 'visit', 'credentialRequest.student']);
        /** @var CredentialRequest $request */
        $request = $event->credentialRequest;

        $this->notifyRequester($event, $request);
        $this->notifyOffice($event, $request);
        $this->notifyRegistrars($event, $request);
        $this->notifyOutgoing($event, $request);
        $this->notifyUnlocked($event, $request);
    }

    /**
     * The Admin unlocked an absent evaluator's request: it waits in Initial Evaluation for any
     * registrar, like a new one (REQ-35-12, REQ-06-21).
     */
    private function notifyUnlocked(RequestEvent $event, CredentialRequest $request): void
    {
        if ($event->type === RequestEventType::EvaluationUnlocked) {
            $this->send(RequestRegistrars::all(), $event, "{$request->reference_no} is back in Initial Evaluation");
        }
    }

    /**
     * An office's staff finished with the paper, or the Admin recorded that for them through an
     * override, and it waits in Outgoing for the Registrar to carry it on (REQ-32-11, REQ-35-12).
     * Not when it moved on at once (after the final check, to the same office, or forwarded in
     * the same step), and never for a registrar's own task.
     */
    private function notifyOutgoing(RequestEvent $event, CredentialRequest $request): void
    {
        $visit = $event->visit;

        // Read after the step was saved: a paper that moved on has its visit completed.
        if (! in_array($event->type, self::FOR_OUTGOING, true) || $visit?->status !== VisitStatus::Done) {
            return;
        }

        if (! $event->isOverride() && ! User::whereKey($event->actor_id)->where('role', Role::Department)->exists()) {
            return;
        }

        $office = $visit->department()->value('name');

        $this->send(RequestRegistrars::for($request), $event,
            "{$request->reference_no} is ready to collect from {$office}");
    }

    private function notifyRequester(RequestEvent $event, CredentialRequest $request): void
    {
        $owner = $request->student;

        // A walk-in request not linked to an account yet has no one to tell (REQ-26-09).
        if ($owner === null) {
            return;
        }

        $ownAction = $event->actor_id === $owner->id && $event->type !== RequestEventType::Submitted;

        if ($event->type->isInternal() || $ownAction || ! $owner->is_active) {
            return;
        }

        $owner->notify(new RequestActivity($event, $event->summary()));
    }

    private function notifyOffice(RequestEvent $event, CredentialRequest $request): void
    {
        $visit = $event->visit;

        if ($event->type !== RequestEventType::DocumentForwarded || $visit === null) {
            return;
        }

        // At the Office of the Registrar: its department staff and the request's registrar (REQ-06-21).
        $recipients = OfficeStaff::of($visit->department()->firstOrFail());

        if ($recipients->contains(fn (User $user): bool => $user->hasRole(Role::Registrar))) {
            $recipients = $recipients->reject(fn (User $user): bool => $user->hasRole(Role::Registrar))
                ->merge(RequestRegistrars::for($request));
        }

        $title = match ($visit->purpose) {
            VisitPurpose::Correction => "{$request->reference_no} was returned to your office for correction",
            VisitPurpose::Resubmission => "{$request->reference_no} was corrected and sent back to you",
            VisitPurpose::Route => "{$request->reference_no} was forwarded to your office",
        };

        $this->send($recipients, $event, $title);
    }

    private function notifyRegistrars(RequestEvent $event, CredentialRequest $request): void
    {
        $byOwner = $event->actor_id === $request->user_id;

        if (! in_array($event->type, self::FOR_REGISTRARS, true) || ! $byOwner) {
            return;
        }

        $title = match ($event->type) {
            RequestEventType::Submitted => "New request {$request->reference_no} to evaluate",
            RequestEventType::PaymentSubmitted => "Payment to verify for {$request->reference_no}",
            RequestEventType::Resubmitted => "{$request->reference_no} was resubmitted for evaluation",
            RequestEventType::CustomerResponded => "The requester replied on {$request->reference_no}",
            RequestEventType::IdentityResubmitted => "Valid ID to verify for {$request->reference_no}",
            default => "Representative to verify for {$request->reference_no}",
        };

        // A proof of payment is the cashier's to validate (REQ-25-08); a new request is for any
        // registrar to take up; the rest go to the request's evaluator (REQ-06-21).
        $recipients = match ($event->type) {
            RequestEventType::PaymentSubmitted => User::where('role', Role::Cashier)->where('is_active', true)->get(),
            RequestEventType::Submitted => RequestRegistrars::all(),
            default => RequestRegistrars::for($request),
        };

        $this->send($recipients, $event, $title);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function send(Collection $users, RequestEvent $event, string $title): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, new RequestActivity($event, $title));
        }
    }
}
