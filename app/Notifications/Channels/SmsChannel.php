<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Support\Sms\SmsRouter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The SMS notification channel (spec 36): the notification's `toSms()` text to the requester's
 * number (an account's, or a walk-in request's through an on-demand notifiable), through IPROG SMS. A failure is logged and goes no further, so the in-app notification
 * and the email still go out (REQ-36-09).
 */
class SmsChannel
{
    public function __construct(private SmsRouter $router) {}

    public function send(User|AnonymousNotifiable $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor(self::class, $notification)
            ?? $notifiable->routeNotificationFor('sms', $notification);

        if (blank($to) || ! method_exists($notification, 'toSms')) {
            return;
        }

        $who = $notifiable instanceof User ? "user {$notifiable->id}" : 'a walk-in client';
        $text = (string) $notification->toSms($notifiable);
        $to = $this->recipient((string) $to);

        if ($to === null) {
            Log::info("SMS to {$who} (not sent: outside production with no SMS_REDIRECT_TO)", ['text' => $text]);

            return;
        }

        $sender = $this->router->sender();

        try {
            $sender->send($to, $text);
        } catch (Throwable $e) {
            Log::warning("SMS to {$who} through {$sender->name()} failed: {$e->getMessage()}");
        }
    }

    /**
     * Outside production, the one number every text goes to, or none (REQ-36-11).
     */
    private function recipient(string $to): ?string
    {
        if (! config('services.sms.redirect')) {
            return $to;
        }

        $redirect = (string) config('services.sms.redirect_to');

        return filled($redirect) ? $redirect : null;
    }
}
