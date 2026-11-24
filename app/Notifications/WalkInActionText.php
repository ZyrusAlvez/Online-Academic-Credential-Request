<?php

namespace App\Notifications;

use App\Models\CredentialRequest;
use App\Models\RequestEvent;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * An [ACTION REQUIRED] notice on a walk-in request not linked to an account yet, texted to the
 * request's own contact number (REQ-36-05). No link: the client has no account to open it with.
 */
class WalkInActionText extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RequestEvent $event) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(object $notifiable): string
    {
        return self::text($this->event, $this->event->credentialRequest()->firstOrFail());
    }

    /**
     * What every [ACTION REQUIRED] text says (REQ-36-08), e.g. "UPHSL: [ACTION REQUIRED]
     * R-2026-00012: Payment instructions available."
     */
    public static function text(RequestEvent $event, CredentialRequest $request): string
    {
        $event->loadMissing(['department', 'step']);

        return "UPHSL: [ACTION REQUIRED] {$request->reference_no}: {$event->summary()}";
    }
}
