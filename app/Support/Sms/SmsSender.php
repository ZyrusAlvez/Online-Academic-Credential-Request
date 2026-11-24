<?php

namespace App\Support\Sms;

/**
 * One SMS provider (REQ-36-07).
 */
interface SmsSender
{
    /**
     * Send one text to an E.164 number.
     *
     * @throws SmsFailed when the provider refuses it or cannot be reached
     */
    public function send(string $to, string $text): void;

    /** The provider's name, for the log and `app:test-sms`. */
    public function name(): string;
}
