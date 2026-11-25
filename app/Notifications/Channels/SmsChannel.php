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
 * number (an account's, or a walk-in request's through an on-demand notifiable), through IPROG SMS
 * or only to the log ({@see SmsRouter}). A failure is logged and goes no further, so the in-app
 * notification and the email still go out (REQ-36-09).
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
        $sender = $this->router->sender();

        try {
            $sender->send((string) $to, (string) $notification->toSms($notifiable));
        } catch (Throwable $e) {
            Log::warning("SMS to {$who} through {$sender->name()} failed: {$e->getMessage()}");
        }
    }
}
