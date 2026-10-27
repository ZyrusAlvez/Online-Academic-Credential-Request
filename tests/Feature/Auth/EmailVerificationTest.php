<?php

use App\Actions\Auth\ConfirmVerificationCode;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(fn () => Notification::fake());

afterEach(fn () => Carbon::setTestNow());

/** The code most recently emailed to the user. */
function sentCode(User $user): string
{
    return Notification::sent($user, VerificationCode::class)->last()->code;
}

test('email verification screen can be rendered', function () {
    $this->actingAs(User::factory()->unverified()->create())->get('/verify-email')->assertOk()->assertSee('Verification code');
});

test('REQ-01-09 unverified users are sent to the verify-email notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('student.dashboard'))->assertRedirect(route('verification.notice'));
});

test('REQ-01-23 the emailed code verifies the email address', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Volt::actingAs($user)->test('auth.verify-email')
        ->set('code', sentCode($user))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(EmailVerificationCode::count())->toBe(0);
});

test('REQ-01-23 a wrong code is refused and the code stops working after 5 wrong tries', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $code = sentCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    $page = Volt::actingAs($user)->test('auth.verify-email');

    foreach (range(1, ConfirmVerificationCode::MAX_ATTEMPTS) as $try) {
        $page->set('code', $wrong)->call('verify')->assertHasErrors('code');
    }

    $page->set('code', $code)->call('verify')->assertHasErrors('code');
    $page->set('code', 'abc')->call('verify')->assertHasErrors(['code' => 'digits']);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and(EmailVerificationCode::sole()->attempts)->toBe(ConfirmVerificationCode::MAX_ATTEMPTS);
});

test('REQ-01-23 a code expires after 10 minutes; opening the page then sends a new one', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $expired = sentCode($user);

    Carbon::setTestNow(now()->addMinutes(11));

    $page = Volt::actingAs($user)->test('auth.verify-email');
    Notification::assertSentToTimes($user, VerificationCode::class, 2);

    if ($expired !== sentCode($user)) {
        $page->set('code', $expired)->call('verify')->assertHasErrors('code');
    }

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    $page->set('code', sentCode($user))->call('verify')->assertHasNoErrors();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('REQ-01-23 an expired code is refused', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Carbon::setTestNow(now()->addMinutes(11));

    expect(fn () => app(ConfirmVerificationCode::class)->handle($user, sentCode($user)))
        ->toThrow(ValidationException::class, 'This code has expired.');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('REQ-01-23 a new code can be sent only every 30 seconds and replaces the old one', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $first = sentCode($user);

    $page = Volt::actingAs($user)->test('auth.verify-email')->assertSet('cooldown', fn (int $s) => $s > 0 && $s <= 30);
    $page->call('resend')->assertHasErrors('code');
    Notification::assertSentToTimes($user, VerificationCode::class, 1);

    Carbon::setTestNow(now()->addSeconds(31));
    $page->call('resend')->assertHasNoErrors()->assertDispatched('code-sent');
    Notification::assertSentToTimes($user, VerificationCode::class, 2);

    if ($first !== sentCode($user)) {
        $page->set('code', $first)->call('verify')->assertHasErrors('code');
    }

    $page->set('code', sentCode($user))->call('verify')->assertHasNoErrors();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('REQ-01-23 the verify page sends a code when the user has none, e.g. after changing their email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    Notification::assertSentToTimes($user, VerificationCode::class, 1);

    $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    Notification::assertSentToTimes($user, VerificationCode::class, 1);
});

test('REQ-01-23 only a hash of the code is stored', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    expect(EmailVerificationCode::sole()->code_hash)->not->toBe(sentCode($user))->not->toContain(sentCode($user));
});
