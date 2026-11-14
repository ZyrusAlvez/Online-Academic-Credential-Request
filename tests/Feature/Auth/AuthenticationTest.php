<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk();
});

test('REQ-01-05 users can log in with email and password', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('REQ-01-05 users cannot log in with an invalid password', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('REQ-01-06 login is locked after 5 failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        Volt::test('auth.login')->set('email', $user->email)->set('password', 'wrong')->call('login');
    }

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('REQ-01-07 deactivated users cannot log in', function () {
    $user = User::factory()->inactive()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => __('auth.deactivated')]);

    $this->assertGuest();
});

test('REQ-01-07 a deactivated account is refused before it is signed in, and a wrong password still says so', function () {
    $user = User::factory()->inactive()->create();

    Volt::test('auth.login')->set('email', $user->email)->set('password', 'wrong')->call('login')
        ->assertHasErrors(['email' => __('auth.failed')]);

    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login')
        ->assertHasErrors(['email' => __('auth.deactivated')]);

    // The log shows two failed attempts and never a sign-in or sign-out (REQ-14-05).
    expect(ActivityLog::pluck('action')->all())->toBe(['sign_in.failed', 'sign_in.failed']);
    $this->assertGuest();
});

test('REQ-14-05 a remember-me sign-in of a deactivated account is logged as refused', function () {
    $user = User::factory()->inactive()->create();

    event(new Login('web', $user, true));
    event(new Logout('web', $user));

    expect(ActivityLog::orderBy('id')->pluck('description')->all())->toBe([
        'Sign-in refused: the account is deactivated',
        'Signed out: the account is deactivated',
    ]);
});

test('REQ-01-08 a user deactivated mid-session is logged out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('student.dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('student.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
