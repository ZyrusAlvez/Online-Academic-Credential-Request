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

    /** Event types students are also emailed about. */
    public const MAILED_TO_STUDENTS = [
        RequestEventType::Submitted,
        RequestEventType::PaymentRejected,
        RequestEventType::Routed,
        RequestEventType::StepSigned,
        RequestEventType::StepHeld,
        RequestEventType::StepSkipped,
        RequestEventType::ReadyForRelease,
        RequestEventType::Cancelled,
    ];

    public function __construct(public RequestEvent $event, public string $title) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $mail = $notifiable->hasRole(Role::Student) && in_array($this->event->type, self::MAILED_TO_STUDENTS, true);

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
        $this->event->loadMissing('department');

        return $this->event->credentialRequest()->with(['credentialType', 'currentStep.department'])->firstOrFail();
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
