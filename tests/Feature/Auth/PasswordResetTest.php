<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('reset password link screen can be rendered', function () {
    $this->get('/forgot-password')->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();
    $user = User::factory()->create();

    Volt::test('auth.forgot-password')->set('email', $user->email)->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('password can be reset with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    Volt::test('auth.forgot-password')->set('email', $user->email)->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get('/reset-password/'.$notification->token)->assertOk();

        Volt::test('auth.reset-password', ['token' => $notification->token])
            ->set('email', $user->email)
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});
