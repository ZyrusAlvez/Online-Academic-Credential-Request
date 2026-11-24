<?php

namespace App\Support\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Telnyx, for numbers outside the Philippines (REQ-36-07):
 * https://developers.telnyx.com/api/messaging/send-message
 */
class TelnyxSender implements SmsSender
{
    public const URL = 'https://api.telnyx.com/v2/messages';

    /**
     * @param  string  $from  a Telnyx number or an alphanumeric sender ID
     * @param  ?string  $messagingProfileId  needed with an alphanumeric sender ID
     */
    public function __construct(private string $key, private string $from, private ?string $messagingProfileId = null) {}

    public function send(string $to, string $text): void
    {
        try {
            $response = Http::withToken($this->key)
                ->acceptJson()
                ->timeout(10)
                ->post(self::URL, array_filter([
                    'from' => $this->from,
                    'to' => $to,
                    'text' => $text,
                    'messaging_profile_id' => $this->messagingProfileId,
                ]));
        } catch (ConnectionException $e) {
            throw new SmsFailed("Telnyx could not be reached: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new SmsFailed('Telnyx refused the text: '.($response->json('errors.0.detail') ?? $response->json('errors.0.title') ?? "HTTP {$response->status()}"));
        }
    }

    public function name(): string
    {
        return 'Telnyx';
    }
}
