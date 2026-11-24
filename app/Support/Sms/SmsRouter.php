<?php

namespace App\Support\Sms;

use App\Support\PhoneNumbers;

/**
 * Picks the cheapest provider for a number (REQ-36-07): PhilSMS for +63, Telnyx for the rest, and
 * the log while that provider's keys are not set.
 */
class SmsRouter
{
    public function for(string $to): SmsSender
    {
        return PhoneNumbers::isPhilippine($to) ? $this->philSms() : $this->telnyx();
    }

    private function philSms(): SmsSender
    {
        $token = (string) config('services.philsms.token');

        return filled($token)
            ? new PhilSmsSender($token, (string) config('services.philsms.sender_id'))
            : new LogSmsSender('PhilSMS');
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
