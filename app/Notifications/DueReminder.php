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
 * A request is due soon or overdue (REQ-06-11..13, REQ-06-20): to the holding office's staff,
 * every registrar, and (overdue only) the requester; in-app, and by email unless switched off.
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
        return ! $notifiable->hasRole(Role::Admin) && $notifiable->wantsEmailNotifications() ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$this->request->reference_no}: {$this->title($notifiable)}")
            ->greeting($notifiable->greeting())
            ->line($this->message($notifiable));

        if (! $notifiable->hasRole(Role::Student)) {
            return $message
                ->action('Open request', $this->url($notifiable))
                ->salutation((string) config('app.name'));
        }

        return $message
            ->line('We are sorry for the delay. You can follow your request at any time.')
            ->action('Track your request', $this->url($notifiable))
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
            'url' => $this->url($notifiable),
        ];
    }

    private function url(User $notifiable): string
    {
        return route(match ($notifiable->role) {
            Role::Student => 'student.requests.show',
            Role::Department => 'department.requests.show',
            Role::Registrar => 'registrar.requests.show',
            Role::Admin => 'records.show',   // admins are not reminded; the read-only record (REQ-18-06)
        }, $this->request);
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

        // Registrars are told about every request, wherever the paper is (REQ-06-20).
        if ($notifiable->hasRole(Role::Registrar)) {
            $where = $this->request->staffWhereabouts();

            return $this->overdue
                ? "It was due {$this->due()}. Current location: {$where}."
                : "It is due {$this->due()}. Current location: {$where}.";
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
