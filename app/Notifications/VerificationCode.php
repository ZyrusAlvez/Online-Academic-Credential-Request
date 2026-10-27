<?php

namespace App\Notifications;

use App\Actions\Auth\SendVerificationCode;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time code that confirms a new email address (REQ-01-23). Sent right away, not queued, so
 * the code arrives while the user waits on the verify page.
 */
class VerificationCode extends Notification
{
    public function __construct(public string $code) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your verification code: {$this->code}")
            ->greeting($notifiable->greeting())
            ->line('Enter this code to verify your email address:')
            ->line("# {$this->code}")
            ->line('The code expires in '.SendVerificationCode::EXPIRES_MINUTES.' minutes. If you did not create an account, you can ignore this email.')
            ->salutation('Office of the Registrar, University of Perpetual Help System');
    }
}
