<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $this->actingAs(User::factory()->unverified()->create())->get('/verify-email')->assertOk();
});

test('REQ-01-09 unverified users are sent to the verify-email notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('student.dashboard'))->assertRedirect(route('verification.notice'));
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id, 'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id, 'hash' => sha1('wrong-email'),
    ]);

    $this->actingAs($user)->get($url);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
