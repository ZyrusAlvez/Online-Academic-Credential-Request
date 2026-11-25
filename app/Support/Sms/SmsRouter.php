<?php

namespace App\Support\Sms;

use App\Models\Setting;
use App\Support\PhoneNumbers;

/**
 * Where texts go (REQ-36-07, 11, 12): IPROG SMS, or only the log while its key is not set, on a
 * developer's machine, or while the Admin has texting turned off. Only Philippine numbers are
 * texted ({@see PhoneNumbers::isTextable()}).
 */
class SmsRouter
{
    public function sender(): SmsSender
    {
        $iprog = $this->iprog();

        return match (true) {
            $iprog === null => new LogSmsSender('the IPROG SMS key is not set'),
            ! config('services.sms.send') => new LogSmsSender('texts are only logged on a developer\'s machine'),
            ! Setting::enabled(Setting::SMS_ENABLED) => new LogSmsSender('the Admin has turned texting off'),
            default => $iprog,
        };
    }

    /** The provider itself, whatever the switch, e.g. for its balance (REQ-36-13); null without a key. */
    public function iprog(): ?IprogSender
    {
        $token = (string) config('services.iprog.token');

        return filled($token) ? new IprogSender($token) : null;
    }
}
