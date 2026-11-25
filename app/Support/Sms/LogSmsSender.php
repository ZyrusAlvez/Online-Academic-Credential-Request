<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes texts to the log instead of sending them (REQ-36-07, 11, 12), like the `log` mailer.
 */
class LogSmsSender implements SmsSender
{
    /**
     * @param  string  $reason  why the text is not sent, e.g. "the IPROG SMS key is not set"
     */
    public function __construct(private string $reason) {}

    public function send(string $to, string $text): void
    {
        Log::info("SMS to {$to} (not sent: {$this->reason})", ['text' => $text]);
    }

    public function name(): string
    {
        return 'log';
    }
}
