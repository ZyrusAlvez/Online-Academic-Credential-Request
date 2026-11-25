<?php

namespace App\Support\Sms;

use App\Support\PhoneNumbers;

/**
 * The provider texts go through (REQ-36-07): IPROG SMS, or the log while its key is not set. Only
 * Philippine numbers are texted ({@see PhoneNumbers::isTextable()}).
 */
class SmsRouter
{
    public function sender(): SmsSender
    {
        $token = (string) config('services.iprog.token');

        return filled($token) ? new IprogSender($token) : new LogSmsSender('IPROG SMS');
    }
}
