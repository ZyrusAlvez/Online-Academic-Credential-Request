<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A request is due soon or overdue (REQ-06-11..13). Offices get it in-app; the requester also by
 * email.
 */
class DueReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CredentialRequest $request, public bool $overdue) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->hasRole(Role::Student) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->request->reference_no}: {$this->title($notifiable)}")
            ->greeting($notifiable->greeting())
            ->line($this->message($notifiable))
            ->line('We are sorry for the delay. You can follow your request at any time.')
            ->action('Track your request', route('student.requests.show', $this->request))
            ->salutation('Office of the Registrar, University of Perpetual Help System');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'request_id' => $this->request->id,
            'reference_no' => $this->request->reference_no,
            'title' => $this->title($notifiable),
            'message' => $this->message($notifiable),
            'url' => route(match ($notifiable->role) {
                Role::Student => 'student.requests.show',
                Role::Department => 'department.requests.show',
                Role::Registrar => 'registrar.requests.show',
            }, $this->request),
        ];
    }

    private function title(User $notifiable): string
    {
        if ($notifiable->hasRole(Role::Student)) {
            return 'Your request is taking longer than expected';
        }

        return $this->overdue
            ? "{$this->request->reference_no} is overdue"
            : "{$this->request->reference_no} is due {$this->due()}";
    }

    private function message(User $notifiable): string
    {
        if ($notifiable->hasRole(Role::Student)) {
            return "Your {$this->request->credentialType->name} request was expected to be ready by {$this->due()}. It is still being processed.";
        }

        return $this->overdue
            ? "It was due {$this->due()} and is with your office now."
            : "It is with your office and due {$this->due()}.";
    }

    private function due(): string
    {
        return (string) $this->request->effective_due_at?->format('l, M j');
    }
}
