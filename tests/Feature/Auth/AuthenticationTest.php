<?php

use App\Models\User;
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
