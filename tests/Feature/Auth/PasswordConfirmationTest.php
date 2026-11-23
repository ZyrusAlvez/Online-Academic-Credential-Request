<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('password is not confirmed with an invalid password', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('auth.confirm-password')
        ->set('password', 'wrong-password')
        ->call('confirmPassword')
        ->assertHasErrors(['password']);
});
