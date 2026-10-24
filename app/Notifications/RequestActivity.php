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
 * Something happened on a credential request that this user should know about.
 */
class RequestActivity extends Notification implements ShouldQueue
{
    use Queueable;

    /** Event types requesters are also emailed about (REQ-06-05). */
    public const MAILED_TO_REQUESTERS = [
        RequestEventType::Submitted,
        RequestEventType::ComplianceRequested,
        RequestEventType::CustomerActionRequested,
        RequestEventType::RequirementsCompleted,
        RequestEventType::PaymentRejected,
        RequestEventType::Accepted,
        RequestEventType::ReadyForRelease,
        RequestEventType::Declined,
        RequestEventType::Cancelled,
    ];

    public function __construct(public RequestEvent $event, public string $title) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $mail = $notifiable->hasRole(Role::Student) && in_array($this->event->type, self::MAILED_TO_REQUESTERS, true);

        return $mail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $request = $this->request();
        $message = (new MailMessage)
            ->subject("{$request->reference_no}: {$this->title}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Update on your {$request->credentialType->name} request ({$request->reference_no}):")
            ->line("**{$this->event->summary()}**");

        if (filled($this->event->remarks)) {
            $message->line("Remarks: {$this->event->remarks}");
        }

        if ($this->event->type === RequestEventType::RequirementsCompleted && $request->amount_due !== null) {
            $message->line('Amount due: ₱'.number_format((float) $request->amount_due, 2).'. Pay on the UPHSL online payment portal, then upload your proof of payment on your request page.');
        }

        if ($this->event->type === RequestEventType::Accepted && $request->effective_due_at !== null) {
            $message->line('Expected release date: '.$request->effective_due_at->format('l, F j, Y').'.');
        }

        if ($this->event->type === RequestEventType::ReadyForRelease) {
            $message->line('Claim it at the Office of the Registrar with a valid ID. A representative must present a Special Power of Attorney and the valid IDs of both of you.');
        }

        return $message
            ->line('Current location: '.$request->whereabouts())
            ->action('Track your request', $this->url($notifiable, $request))
            ->salutation('Office of the Registrar, University of Perpetual Help System');
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
            'message' => $this->event->summary().(filled($this->event->remarks) ? " – {$this->event->remarks}" : ''),
            'url' => $this->url($notifiable, $request),
        ];
    }

    private function request(): CredentialRequest
    {
        $this->event->loadMissing(['department', 'step']);

        return $this->event->credentialRequest()->with(['credentialType', 'openVisit.department'])->firstOrFail();
    }

    private function url(User $notifiable, CredentialRequest $request): string
    {
        $route = match ($notifiable->role) {
            Role::Student => 'student.requests.show',
            Role::Department => 'department.requests.show',
            Role::Registrar => 'registrar.requests.show',
        };

        return route($route, $request);
    }
}
