<?php

use App\Support\Sms\IprogSender;
use Illuminate\Support\Facades\Http;

/*
| REQ-36-10: the keys in this machine's .env are checked against the providers themselves, through
| their free account endpoints: no text is sent and nothing is spent. A provider whose keys are not
| set is skipped (as on CI). The keys are read from the environment, since tests/TestCase.php blanks
| them in the config so that no other test can reach a provider.
*/

const TELNYX_API = 'https://api.telnyx.com/v2';

test('REQ-36-10 the IPROG SMS token in .env is accepted', function () {
    Http::allowStrayRequests([IprogSender::CREDITS_URL.'*']);

    $response = Http::acceptJson()->timeout(15)
        ->get(IprogSender::CREDITS_URL, ['api_token' => (string) env('IPROG_API_TOKEN')]);

    expect($response->json('status'))->toBe('success', 'IPROG SMS refused IPROG_API_TOKEN: '.($response->json('message') ?? "HTTP {$response->status()}"))
        ->and($response->json('data.load_balance'))->toBeNumeric();
})->skip(fn () => blank(env('IPROG_API_TOKEN')), 'IPROG_API_TOKEN is not set in .env');

test('REQ-36-10 the Telnyx key and messaging profile in .env are accepted', function () {
    Http::allowStrayRequests([TELNYX_API.'/*']);
    $telnyx = Http::withToken((string) env('TELNYX_API_KEY'))->acceptJson()->timeout(15);

    $balance = $telnyx->get(TELNYX_API.'/balance');
    expect($balance->status())->toBe(200, 'Telnyx refused TELNYX_API_KEY: '.($balance->json('errors.0.detail') ?? "HTTP {$balance->status()}"));

    $profileId = (string) env('TELNYX_MESSAGING_PROFILE_ID');
    expect($profileId)->not->toBe('', 'TELNYX_MESSAGING_PROFILE_ID is not set: an alphanumeric sender needs it')
        ->and(env('TELNYX_FROM'))->not->toBeEmpty('TELNYX_FROM is not set: the sender ID or number texts come from');

    $profile = $telnyx->get(TELNYX_API.'/messaging_profiles/'.rawurlencode($profileId));
    expect($profile->status())->toBe(200, 'Telnyx has no messaging profile '.$profileId.' on this account: '.($profile->json('errors.0.detail') ?? "HTTP {$profile->status()}"));
})->skip(fn () => blank(env('TELNYX_API_KEY')), 'TELNYX_API_KEY is not set in .env');
