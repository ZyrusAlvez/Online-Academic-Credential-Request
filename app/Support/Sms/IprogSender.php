<?php

namespace App\Support\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * IPROG SMS, for Philippine (+63) numbers (REQ-36-07): https://www.iprogsms.com/api/v1/documentation
 * The sender name is the account's approved one, set on IPROG's dashboard.
 */
class IprogSender implements SmsSender
{
    public const URL = 'https://www.iprogsms.com/api/v1/sms_messages';

    /** Free: the account's credits, to check the token without sending a text (REQ-36-10). */
    public const CREDITS_URL = 'https://www.iprogsms.com/api/v1/account/sms_credits';

    public function __construct(private string $token) {}

    public function send(string $to, string $text): void
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->post(self::URL, [
                    'api_token' => $this->token,
                    'phone_number' => ltrim($to, '+'),   // 639171234567
                    'message' => $text,
                ]);
        } catch (ConnectionException $e) {
            throw new SmsFailed("IPROG SMS could not be reached: {$e->getMessage()}", previous: $e);
        }

        // A queued text replies {"status": 200, ...}; anything else is a refusal.
        if ((int) $response->json('status') !== 200) {
            throw new SmsFailed('IPROG SMS refused the text: '.($response->json('message') ?? "HTTP {$response->status()}"));
        }
    }

    /**
     * The account's credit balance (REQ-36-13). Free: no text is sent.
     *
     * @throws SmsFailed when IPROG refuses the token or cannot be reached
     */
    public function credits(): float
    {
        try {
            $response = Http::acceptJson()->timeout(20)->get(self::CREDITS_URL, ['api_token' => $this->token]);
        } catch (ConnectionException $e) {
            throw new SmsFailed("IPROG SMS could not be reached: {$e->getMessage()}", previous: $e);
        }

        $balance = $response->json('data.load_balance');

        if ($response->json('status') !== 'success' || ! is_numeric($balance)) {
            throw new SmsFailed('IPROG SMS refused the balance check: '.($response->json('message') ?? "HTTP {$response->status()}"));
        }

        return (float) $balance;
    }

    public function name(): string
    {
        return 'IPROG SMS';
    }
}
