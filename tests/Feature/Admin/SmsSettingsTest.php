<?php

use App\Livewire\Admin\Sms\Index;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Sms\IprogSender;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| Administration → SMS (REQ-36-12, 13). Sending itself is in tests/Feature/Notifications/SmsTest.php.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('REQ-36-12 texting is off until the Admin turns it on, and each change is logged', function () {
    expect(Setting::enabled(Setting::SMS_ENABLED))->toBeFalse();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSet('enabled', false)
        ->set('enabled', true);
    expect(Setting::enabled(Setting::SMS_ENABLED))->toBeTrue();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSet('enabled', true)
        ->set('enabled', false);
    expect(Setting::enabled(Setting::SMS_ENABLED))->toBeFalse()
        ->and(ActivityLog::where('description', 'like', '%SMS notifications%')->count())->toBe(2);
});

test('REQ-36-12 only the Admin sees the SMS page', function () {
    $this->actingAs($this->admin)->get(route('admin.sms'))->assertOk();

    foreach ([User::factory()->registrar()->create(), User::factory()->student()->create(), User::factory()->cashier()->create()] as $user) {
        $this->actingAs($user)->get(route('admin.sms'))->assertForbidden();
    }

    Livewire::actingAs(User::factory()->registrar()->create())->test(Index::class)->assertForbidden();
});

test('REQ-36-13 the page shows the IPROG credits, fetched after it loads', function () {
    config(['services.iprog.token' => 'test-token']);
    Http::fake([IprogSender::CREDITS_URL.'*' => Http::response(['status' => 'success', 'message' => 'Account found.', 'data' => ['load_balance' => 245.5]])]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSet('balanceProblem', 'loading')   // not fetched while the page renders
        ->call('loadBalance')
        ->assertSet('balance', 245.5)
        ->assertSet('balanceProblem', null)
        ->assertSeeHtml('data-sms-credits');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), IprogSender::CREDITS_URL) && $request['api_token'] === 'test-token');
});

test('REQ-36-13 without the key, or when IPROG refuses, the page says so', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('loadBalance')
        ->assertSet('balanceProblem', 'no_key')
        ->assertSeeHtml('data-sms-no-key');

    config(['services.iprog.token' => 'bad-token']);
    Http::fake([IprogSender::CREDITS_URL.'*' => Http::response(['status' => 500, 'message' => 'Invalid Token'])]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('loadBalance')
        ->assertSet('balance', null)
        ->assertSet('balanceProblem', 'unreachable')
        ->assertSeeHtml('data-sms-unreachable');
});
