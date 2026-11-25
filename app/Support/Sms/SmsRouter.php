<?php

namespace App\Support\Sms;

use App\Support\PhoneNumbers;

/**
 * Picks the cheapest provider for a number (REQ-36-07): IPROG SMS for +63, Telnyx for the rest, and
 * the log while that provider's keys are not set.
 */
class SmsRouter
{
    public function for(string $to): SmsSender
    {
        return PhoneNumbers::isPhilippine($to) ? $this->iprog() : $this->telnyx();
    }

    private function iprog(): SmsSender
    {
        $token = (string) config('services.iprog.token');

        return filled($token) ? new IprogSender($token) : new LogSmsSender('IPROG SMS');
    }

    private function telnyx(): SmsSender
    {
        $key = (string) config('services.telnyx.key');
        $profile = (string) config('services.telnyx.messaging_profile_id');

        return filled($key)
            ? new TelnyxSender($key, (string) config('services.telnyx.from'), filled($profile) ? $profile : null)
            : new LogSmsSender('Telnyx');
    }
}
