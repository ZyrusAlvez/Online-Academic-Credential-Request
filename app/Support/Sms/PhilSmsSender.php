<?php

namespace App\Support\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * PhilSMS, for Philippine (+63) numbers (REQ-36-07). API v3:
 * https://app.philsms.com/developers/documentation
 */
class PhilSmsSender implements SmsSender
{
    public const URL = 'https://app.philsms.com/api/v3/sms/send';

    public function __construct(private string $token, private string $senderId) {}

    public function send(string $to, string $text): void
    {
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(10)
                ->post(self::URL, [
                    'recipient' => ltrim($to, '+'),   // 639171234567
                    'sender_id' => $this->senderId,
                    'type' => 'plain',
                    'message' => $text,
                ]);
        } catch (ConnectionException $e) {
            throw new SmsFailed("PhilSMS could not be reached: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new SmsFailed('PhilSMS refused the text: '.($response->json('message') ?? "HTTP {$response->status()}"));
        }
    }

    public function name(): string
    {
        return 'PhilSMS';
    }
}
