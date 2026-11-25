<?php

use App\Support\Sms\IprogSender;
use Illuminate\Support\Facades\Http;

/*
| REQ-36-10: the IPROG SMS key in this machine's .env is checked against IPROG itself, through its
| free credit-balance endpoint: no text is sent and nothing is spent. Skipped when the key is not set
| (as on CI). The key is read from the environment, since tests/TestCase.php blanks it in the config
| so that no other test can reach the provider.
*/

test('REQ-36-10 the IPROG SMS token in .env is accepted', function () {
    Http::allowStrayRequests([IprogSender::CREDITS_URL.'*']);

    $response = Http::acceptJson()->timeout(15)
        ->get(IprogSender::CREDITS_URL, ['api_token' => (string) env('IPROG_API_TOKEN')]);

    expect($response->json('status'))->toBe('success', 'IPROG SMS refused IPROG_API_TOKEN: '.($response->json('message') ?? "HTTP {$response->status()}"))
        ->and($response->json('data.load_balance'))->toBeNumeric();
})->skip(fn () => blank(env('IPROG_API_TOKEN')), 'IPROG_API_TOKEN is not set in .env');
