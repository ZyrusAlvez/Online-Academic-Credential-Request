<?php

namespace App\Listeners;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\StepStatus;
use App\Events\RequestEventRecorded;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\RequestEvent;
use App\Models\User;
use App\Notifications\RequestActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about a ledger event: the student, the office the document just
 * reached, and registrars when something needs review.
 */
class SendRequestNotifications
{
    /** Events that hand the document to the next office. */
    private const HAND_OFFS = [RequestEventType::Routed, RequestEventType::StepSigned, RequestEventType::StepSkipped];

    public function handle(RequestEventRecorded $recorded): void
    {
        $event = $recorded->event->loadMissing(['department', 'credentialRequest.student']);
        /** @var CredentialRequest $request */
        $request = $event->credentialRequest;

        $this->notifyStudent($event, $request);
        $this->notifyArrivingOffice($event, $request);
        $this->notifyRegistrarsOfReview($event, $request);
    }

    private function notifyStudent(RequestEvent $event, CredentialRequest $request): void
    {
        $student = $request->student;

        $internal = in_array($event->type, [RequestEventType::PaymentSubmitted, RequestEventType::PaymentAccepted], true);
        $ownAction = $event->actor_id === $student->id && $event->type !== RequestEventType::Submitted;

        if ($internal || $ownAction || ! $student->is_active) {
            return;
        }

        $student->notify(new RequestActivity($event, $event->summary()));
    }

    private function notifyArrivingOffice(RequestEvent $event, CredentialRequest $request): void
    {
        if (! in_array($event->type, self::HAND_OFFS, true) || $request->status !== RequestStatus::InProgress) {
            return;
        }

        $step = $request->steps()->with('department')->where('status', StepStatus::Pending)->first();

        if ($step === null) {
            return;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('role', Role::Department)->where('department_id', $step->department_id))
                ->when(
                    $step->department->code === Department::REGISTRAR_CODE,
                    fn ($q) => $q->orWhere('role', Role::Registrar),
                ))
            ->get();

        $this->send($recipients, $event, "{$request->reference_no} is waiting for your signature");
    }

    private function notifyRegistrarsOfReview(RequestEvent $event, CredentialRequest $request): void
    {
        $needsReview = $event->type === RequestEventType::PaymentSubmitted
            || ($event->type === RequestEventType::Submitted && $event->to_status === RequestStatus::RegistrarReview);

        if (! $needsReview) {
            return;
        }

        $title = $event->type === RequestEventType::PaymentSubmitted
            ? "Receipt to verify for {$request->reference_no}"
            : "New request {$request->reference_no} to review";

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
