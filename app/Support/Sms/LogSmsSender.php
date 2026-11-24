<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes texts to the log instead of sending them, while a provider's keys are not set
 * (REQ-36-07), like the `log` mailer.
 */
class LogSmsSender implements SmsSender
{
    public function __construct(private string $provider) {}

    public function send(string $to, string $text): void
    {
        Log::info("SMS to {$to} (not sent: {$this->provider} keys not set)", ['text' => $text]);
    }

    public function name(): string
    {
        return "log (no {$this->provider} keys)";
    }
}
