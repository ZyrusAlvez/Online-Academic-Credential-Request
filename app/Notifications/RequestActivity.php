<?php

namespace App\Notifications;

use App\Enums\RequestEventType;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Something happened on a credential request that this user should know about. Sent to each
 * recipient separately, so each account has its own read status (REQ-06-19).
 */
class RequestActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RequestEvent $event, public string $title) {}

    /**
     * In-app always; by email when the account wants it, and always when the requester must act
     * (REQ-06-16, REQ-06-18). Admins are never notified, so never emailed.
     *
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $mail = ! $notifiable->hasRole(Role::Admin)
            && ($notifiable->wantsEmailNotifications() || $this->actionRequired($notifiable));

        return $mail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $request = $this->request();

        return $notifiable->hasRole(Role::Student)
            ? $this->requesterMail($notifiable, $request)
            : $this->staffMail($notifiable, $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $request = $this->request();

        return [
            'request_id' => $request->id,
            'reference_no' => $request->reference_no,
            'event_type' => $this->event->type->value,
            'title' => $this->title,
            'message' => $this->event->summary().(filled($this->event->remarks) ? " – {$this->event->displayRemarks()}" : ''),
            'url' => $this->url($notifiable, $request),
            'action_required' => $this->actionRequired($notifiable),
        ];
    }

    /**
     * Only requesters are asked to act (REQ-06-18).
     */
    public function actionRequired(User $notifiable): bool
    {
        return $notifiable->hasRole(Role::Student)
            && $this->event->type->requiresRequesterAction($this->event->loadMissing('credentialRequest')->credentialRequest);
    }

    private function requesterMail(User $notifiable, CredentialRequest $request): MailMessage
    {
        $prefix = $this->actionRequired($notifiable) ? '[ACTION REQUIRED] ' : '';
        $message = (new MailMessage)
            ->subject("{$prefix}{$request->reference_no}: {$this->title}")
            ->greeting($notifiable->greeting())
            ->line("Update on your {$request->credentialType->name} request ({$request->reference_no}):")
            ->line("**{$this->event->summary()}**");

        if (filled($this->event->remarks)) {
            $message->line("Remarks: {$this->event->displayRemarks()}");
        }

        if ($this->event->type === RequestEventType::RequirementsCompleted && $request->amount_due !== null) {
            $message->line('Amount due: ₱'.number_format((float) $request->amount_due, 2).'. Pay on the UPHSL online payment portal, then upload your proof of payment on your request page.');
        }

        if ($this->event->type === RequestEventType::Accepted && $request->effective_due_at !== null) {
            $message->line('Expected release date: '.$request->effective_due_at->format('l, F j, Y').'.');
        }

        if ($this->event->type === RequestEventType::ReadyForRelease) {
            // A representative only when one was declared on New Request (REQ-11-05).
            $representative = $request->claimingRepresentative();
            $message->line($representative
                ? "Claim it at the Office of the Registrar with a valid ID, or send {$representative->full_name}, your representative, with the Special Power of Attorney and the valid IDs of both of you."
                : 'Claim it at the Office of the Registrar with a valid ID.');
        }

        return $message
            ->line('Current location: '.$request->whereabouts())
            ->action('Track your request', $this->url($notifiable, $request))
            ->salutation('Office of the Registrar, University of Perpetual Help System');
    }

    /**
     * Registrar and office staff (REQ-06-19): which request, what happened, where the paper is.
     */
    private function staffMail(User $notifiable, CredentialRequest $request): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$request->reference_no}: {$this->title}")
            ->greeting($notifiable->greeting())
            ->line("{$request->credentialType->name} requested by {$request->student->fullName()} ({$request->reference_no}):")
            ->line("**{$this->event->summary()}**");

        if (filled($this->event->remarks)) {
            $message->line("Remarks: {$this->event->displayRemarks()}");
        }

        return $message
            ->line('Current location: '.$request->staffWhereabouts())
            ->action('Open request', $this->url($notifiable, $request))
            ->salutation((string) config('app.name'));
    }

    private function request(): CredentialRequest
    {
        $this->event->loadMissing(['department', 'step']);

        return $this->event->credentialRequest()->with(['credentialType', 'student', 'representatives', 'openVisit.department', 'openVisit.fromDepartment'])->firstOrFail();
    }

    private function url(User $notifiable, CredentialRequest $request): string
    {
        $route = match ($notifiable->role) {
            Role::Student => 'student.requests.show',
            Role::Department => 'department.requests.show',
            Role::Registrar => 'registrar.requests.show',
            Role::Admin => 'records.show',   // admins are not notified; the read-only record (REQ-18-06)
        };

        return route($route, $request);
    }
}
