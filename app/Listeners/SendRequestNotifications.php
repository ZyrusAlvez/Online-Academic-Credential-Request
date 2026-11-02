<?php

namespace App\Listeners;

use App\Enums\RequestEventType;
use App\Enums\Role;
use App\Enums\VisitPurpose;
use App\Events\RequestEventRecorded;
use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use App\Models\User;
use App\Notifications\RequestActivity;
use App\Support\OfficeStaff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about a ledger event (spec 06): the requester, the office a document was
 * just forwarded or returned to, and registrars when something needs them.
 */
class SendRequestNotifications
{
    /** Events that need a registrar's attention. */
    private const FOR_REGISTRARS = [
        RequestEventType::Submitted,
        RequestEventType::PaymentSubmitted,
        RequestEventType::CustomerResponded,
        RequestEventType::RepresentativeDeclared,
    ];

    public function handle(RequestEventRecorded $recorded): void
    {
        $event = $recorded->event->loadMissing(['department', 'step', 'visit', 'credentialRequest.student']);
        /** @var CredentialRequest $request */
        $request = $event->credentialRequest;

        $this->notifyRequester($event, $request);
        $this->notifyOffice($event, $request);
        $this->notifyRegistrars($event, $request);
    }

    private function notifyRequester(RequestEvent $event, CredentialRequest $request): void
    {
        $owner = $request->student;
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

        $recipients = OfficeStaff::of($visit->department()->firstOrFail());

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
            RequestEventType::CustomerResponded => "The requester replied on {$request->reference_no}",
            default => "Representative to verify for {$request->reference_no}",
        };

        $this->send(User::where('role', Role::Registrar)->where('is_active', true)->get(), $event, $title);
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
