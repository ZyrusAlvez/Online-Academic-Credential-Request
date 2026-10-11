<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('confirm password screen can be rendered', function () {
    $this->actingAs(User::factory()->create())->get('/confirm-password')->assertOk();
});

test('password can be confirmed', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('auth.confirm-password')
        ->set('password', 'password')
        ->call('confirmPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));
});

test('password is not confirmed with an invalid password', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('auth.confirm-password')
        ->set('password', 'wrong-password')
        ->call('confirmPassword')
        ->assertHasErrors(['password']);
});
