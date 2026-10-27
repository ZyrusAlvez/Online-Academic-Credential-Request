<?php

namespace App\Notifications;

use App\Enums\ReviewStatus;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Registrar verified or rejected a requester's valid ID (spec 01).
 */
class IdentityReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public IdentityDocument $document) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title())
            ->greeting($notifiable->greeting())
            ->line($this->message());

        if (filled($this->document->remarks)) {
            $message->line("Reason: {$this->document->remarks}");
        }

        return $message
            ->action('Open my profile', route('student.profile'))
            ->salutation('Office of the Registrar, University of Perpetual Help System');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message().(filled($this->document->remarks) ? " – {$this->document->remarks}" : ''),
            'url' => route('student.profile'),
        ];
    }

    private function title(): string
    {
        return $this->verified() ? 'Your identity is verified' : 'Your valid ID was not accepted';
    }

    private function message(): string
    {
        return $this->verified()
            ? "The Office of the Registrar verified your {$this->document->id_type}."
            : "The Office of the Registrar could not accept the {$this->document->id_type} you uploaded. Please upload another valid ID.";
    }

    private function verified(): bool
    {
        return $this->document->status === ReviewStatus::Verified;
    }
}
